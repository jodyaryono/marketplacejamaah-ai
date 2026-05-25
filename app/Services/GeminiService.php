<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    private ?string $groqApiKey;
    private string $groqModel;
    private string $groqVisionModel;
    private string $groqEndpoint;

    /** Circuit-breaker: open after this many consecutive failures */
    private const CB_THRESHOLD = 5;
    /** Circuit-breaker: stay open (pause API calls) for this many seconds */
    private const CB_TIMEOUT_SEC = 120;

    private const CB_FAILURES_KEY = 'gemini_cb_failures';
    private const CB_OPEN_UNTIL_KEY = 'gemini_cb_open_until';

    public function __construct()
    {
        // Resolution order:
        //   1. ai_models registry (highest-priority active row per role)
        //   2. legacy settings table rows (gemini_api_key, gemini_model, etc.)
        //   3. .env / config (final fallback so fresh installs work)
        try {
            $primaryText  = AiModel::resolve('primary_text');
            $primaryVis   = AiModel::resolve('primary_vision');
            $fallbackText = AiModel::resolve('fallback_text');
            $fallbackVis  = AiModel::resolve('fallback_vision');
        } catch (\Throwable $e) {
            // ai_models table missing during fresh install / migration
            $primaryText = $primaryVis = $fallbackText = $fallbackVis = null;
        }

        // Gemini-shape fields (used by all `gemini` provider rows)
        $this->apiKey = $primaryText && $primaryText->provider === 'gemini'
            ? ($primaryText->api_key ?? '')
            : (Setting::get('gemini_api_key') ?: (string) config('services.gemini.api_key'));

        $this->model = $primaryText && $primaryText->provider === 'gemini'
            ? $primaryText->model
            : (Setting::get('gemini_model') ?: (string) config('services.gemini.model', 'gemini-flash-latest'));

        $this->endpoint = $primaryText && $primaryText->provider === 'gemini' && !empty($primaryText->endpoint)
            ? rtrim($primaryText->endpoint, '/')
            : (string) config('services.gemini.endpoint');

        // Groq-shape fields (used by fallback_text + fallback_vision when provider=groq/openai/openrouter)
        $this->groqApiKey = $fallbackText && in_array($fallbackText->provider, ['groq', 'openai', 'openrouter'], true)
            ? ($fallbackText->api_key ?? '')
            : (Setting::get('groq_api_key') ?: (string) config('services.groq.api_key'));

        $this->groqModel = $fallbackText && in_array($fallbackText->provider, ['groq', 'openai', 'openrouter'], true)
            ? $fallbackText->model
            : (Setting::get('groq_model') ?: (string) config('services.groq.model', 'llama-3.3-70b-versatile'));

        $this->groqVisionModel = $fallbackVis && in_array($fallbackVis->provider, ['groq', 'openai', 'openrouter'], true)
            ? $fallbackVis->model
            : (Setting::get('groq_vision_model') ?: (string) config('services.groq.vision_model', 'meta-llama/llama-4-scout-17b-16e-instruct'));

        $this->groqEndpoint = $fallbackText && !empty($fallbackText->endpoint)
            ? $fallbackText->endpoint
            : (string) config('services.groq.endpoint', 'https://api.groq.com/openai/v1/chat/completions');
    }

    /**
     * Generate text content from Gemini.
     *
     * @param  int|null $cacheTtl  Cache TTL in minutes. null = no cache.
     */
    public function generateContent(string $prompt, ?int $cacheTtl = null): ?string
    {
        // Serve from cache when TTL is set and a cached entry exists
        if ($cacheTtl !== null) {
            $cacheKey = 'gemini_resp_' . md5($prompt);
            $cached   = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Circuit breaker: skip Gemini call but try Groq fallback directly
        if ($this->isCircuitOpen()) {
            Log::warning('GeminiService: circuit breaker OPEN — falling back to Groq');
            $fallback = $this->callGroq($prompt);
            if ($cacheTtl !== null && $fallback !== null) {
                Cache::put($cacheKey, $fallback, now()->addMinutes($cacheTtl));
            }
            return $fallback;
        }

        $url  = "{$this->endpoint}/{$this->model}:generateContent";
        $body = [
            'contents'        => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 2048],
        ];

        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Content-Type'   => 'application/json',
                    'X-goog-api-key' => $this->apiKey,
                ])->timeout(30)->post($url, $body);

                if ($response->status() === 429) {
                    Log::warning("GeminiService: rate limited (attempt {$attempt}/{$maxAttempts})");
                    if ($attempt < $maxAttempts) {
                        sleep(5);
                        continue;
                    }
                    $this->recordFailure();
                    return $this->fallbackToGroq($prompt, $cacheTtl, $cacheKey ?? null);
                }

                if ($response->failed()) {
                    Log::error('GeminiService::generateContent failed', [
                        'status' => $response->status(),
                        'body'   => mb_substr($response->body(), 0, 300),
                    ]);
                    $this->recordFailure();
                    return $this->fallbackToGroq($prompt, $cacheTtl, $cacheKey ?? null);
                }

                $data   = $response->json();
                $usage  = $data['usageMetadata'] ?? [];
                $this->recordTokenUsage(
                    $usage['promptTokenCount'] ?? 0,
                    $usage['candidatesTokenCount'] ?? 0,
                    'text'
                );
                $this->recordModelUsage(
                    'gemini', $this->model, 'text',
                    $usage['promptTokenCount'] ?? 0,
                    $usage['candidatesTokenCount'] ?? 0
                );

                $result = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

                // Success → reset failure counter
                $this->resetCircuit();

                if ($cacheTtl !== null && $result !== null) {
                    Cache::put($cacheKey, $result, now()->addMinutes($cacheTtl));
                }

                return $result;

            } catch (\Exception $e) {
                Log::error('GeminiService::generateContent exception', ['error' => $e->getMessage()]);
                $this->recordFailure();
                return $this->fallbackToGroq($prompt, $cacheTtl, $cacheKey ?? null);
            }
        }

        return $this->fallbackToGroq($prompt, $cacheTtl, $cacheKey ?? null);
    }

    /**
     * Wrap callGroq with caching so cached fallback responses are reused.
     */
    private function fallbackToGroq(string $prompt, ?int $cacheTtl, ?string $cacheKey): ?string
    {
        $result = $this->callGroq($prompt);
        if ($cacheTtl !== null && $cacheKey !== null && $result !== null) {
            Cache::put($cacheKey, $result, now()->addMinutes($cacheTtl));
        }
        return $result;
    }

    /**
     * Generate a JSON-decoded response from Gemini.
     *
     * @param  int|null $cacheTtl  Cache TTL in minutes. null = no cache.
     */
    public function generateJson(string $prompt, ?int $cacheTtl = null): ?array
    {
        $text = $this->generateContent(
            $prompt . "\n\nRespond ONLY with valid JSON, no markdown, no explanation.",
            $cacheTtl
        );
        if (!$text) return null;

        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*/i', '', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);

        // Fallback: Groq/llama models sometimes prefix with prose ("Here's the JSON: {...}")
        // or wrap a JSON array — extract the first balanced {...} or [...] block.
        if (!is_array($decoded)) {
            $extracted = $this->extractFirstJsonBlock($text);
            if ($extracted !== null) {
                $decoded = json_decode($extracted, true);
            }
        }

        if (!is_array($decoded)) {
            Log::warning('GeminiService::generateJson: non-JSON response', [
                'raw' => mb_substr($text, 0, 300),
            ]);
            return null;
        }
        return $decoded;
    }

    /**
     * Extract the first balanced JSON object or array from a string.
     * Handles strings/escapes so braces inside string literals don't confuse the matcher.
     */
    private function extractFirstJsonBlock(string $text): ?string
    {
        $len = strlen($text);
        $start = -1;
        $open  = '{';
        $close = '}';

        for ($i = 0; $i < $len; $i++) {
            if ($text[$i] === '{' || $text[$i] === '[') {
                $start = $i;
                $open  = $text[$i];
                $close = $open === '{' ? '}' : ']';
                break;
            }
        }
        if ($start === -1) return null;

        $depth    = 0;
        $inString = false;
        $escape   = false;

        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];

            if ($escape) { $escape = false; continue; }
            if ($ch === '\\' && $inString) { $escape = true; continue; }
            if ($ch === '"') { $inString = !$inString; continue; }
            if ($inString) continue;

            if ($ch === $open) $depth++;
            elseif ($ch === $close) {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }
        return null;
    }

    /**
     * Analisa BANYAK gambar dalam SATU panggilan Gemini multimodal.
     * Hemat quota (1 RPM vs N RPM) dan hasil draft lebih koheren karena AI
     * lihat semua foto sekaligus.
     *
     * @param array $images array of ['mime_type' => string, 'data' => base64 string]
     */
    public function analyzeMultipleImagesWithText(array $images, string $prompt): ?string
    {
        if (empty($images)) {
            return null;
        }
        if ($this->isCircuitOpen()) {
            Log::warning('GeminiService: circuit breaker OPEN — falling back to Groq vision multi');
            return $this->callGroqVisionMulti($images, $prompt);
        }

        try {
            $parts = [['text' => $prompt]];
            foreach ($images as $img) {
                $parts[] = ['inline_data' => [
                    'mime_type' => $img['mime_type'] ?? 'image/jpeg',
                    'data'      => $img['data'],
                ]];
            }
            $url      = "{$this->endpoint}/{$this->model}:generateContent";
            $response = Http::withHeaders([
                'Content-Type'   => 'application/json',
                'X-goog-api-key' => $this->apiKey,
            ])->timeout(45)->post($url, [
                'contents' => [['parts' => $parts]],
            ]);

            if ($response->failed()) {
                Log::error('GeminiService::analyzeMultipleImagesWithText failed, fallback to Groq multi-image', [
                    'status' => $response->status(),
                    'body'   => mb_substr($response->body(), 0, 300),
                    'image_count' => count($images),
                ]);
                $this->recordFailure();
                return $this->callGroqVisionMulti($images, $prompt);
            }

            $data  = $response->json();
            $usage = $data['usageMetadata'] ?? [];
            $this->recordTokenUsage(
                $usage['promptTokenCount'] ?? 0,
                $usage['candidatesTokenCount'] ?? 0,
                'image'
            );
            $this->recordModelUsage(
                'gemini', $this->model, 'image',
                $usage['promptTokenCount'] ?? 0,
                $usage['candidatesTokenCount'] ?? 0
            );

            $this->resetCircuit();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (\Exception $e) {
            Log::error('GeminiService::analyzeMultipleImagesWithText exception, fallback to Groq multi-image', ['error' => $e->getMessage()]);
            $this->recordFailure();
            return $this->callGroqVisionMulti($images, $prompt);
        }
    }

    public function analyzeImageWithText(string $base64Image, string $mimeType, string $prompt): ?string
    {
        if ($this->isCircuitOpen()) {
            Log::warning('GeminiService: circuit breaker OPEN — falling back to Groq vision');
            return $this->callGroqVision($base64Image, $mimeType, $prompt);
        }

        try {
            $url      = "{$this->endpoint}/{$this->model}:generateContent";
            $response = Http::withHeaders([
                'Content-Type'   => 'application/json',
                'X-goog-api-key' => $this->apiKey,
            ])->timeout(30)->post($url, [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Image]],
                    ],
                ]],
            ]);

            if ($response->failed()) {
                Log::error('GeminiService::analyzeImageWithText failed', [
                    'status' => $response->status(),
                    'body'   => mb_substr($response->body(), 0, 300),
                ]);
                $this->recordFailure();
                return $this->callGroqVision($base64Image, $mimeType, $prompt);
            }

            $data  = $response->json();
            $usage = $data['usageMetadata'] ?? [];
            $this->recordTokenUsage(
                $usage['promptTokenCount'] ?? 0,
                $usage['candidatesTokenCount'] ?? 0,
                'image'
            );
            $this->recordModelUsage(
                'gemini', $this->model, 'image',
                $usage['promptTokenCount'] ?? 0,
                $usage['candidatesTokenCount'] ?? 0
            );

            $this->resetCircuit();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        } catch (\Exception $e) {
            Log::error('GeminiService::analyzeImageWithText exception', ['error' => $e->getMessage()]);
            $this->recordFailure();
            return $this->callGroqVision($base64Image, $mimeType, $prompt);
        }
    }

    // ── Groq fallback ─────────────────────────────────────────────────────────

    /**
     * Call Groq (OpenAI-compatible chat completions) as a fallback for text generation.
     * Returns null if no API key is configured or the call fails.
     */
    /**
     * Public ping for the Groq fallback path. Returns ['ok'=>bool, 'response'=>string|null,
     * 'error'=>string|null, 'model'=>string]. Used by AiHealthController to verify the
     * fallback works even when primary Gemini is down.
     */
    public function pingGroqFallback(string $prompt = 'Reply with the single word: PONG'): array
    {
        if (empty($this->groqApiKey)) {
            return ['ok' => false, 'response' => null, 'error' => 'GROQ_API_KEY not configured', 'model' => $this->groqModel];
        }
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(15)->post($this->groqEndpoint, [
                'model'       => $this->groqModel,
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0,
                'max_tokens'  => 16,
            ]);
            if ($response->failed()) {
                return ['ok' => false, 'response' => null, 'error' => 'HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 120), 'model' => $this->groqModel];
            }
            $text = trim($response->json('choices.0.message.content') ?? '');
            return ['ok' => str_contains(strtolower($text), 'pong'), 'response' => $text, 'error' => null, 'model' => $this->groqModel];
        } catch (\Throwable $e) {
            return ['ok' => false, 'response' => null, 'error' => $e->getMessage(), 'model' => $this->groqModel];
        }
    }

    private function callGroq(string $prompt): ?string
    {
        if (empty($this->groqApiKey)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post($this->groqEndpoint, [
                'model'       => $this->groqModel,
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.1,
                'max_tokens'  => 2048,
            ]);

            if ($response->failed()) {
                Log::error('GeminiService::callGroq failed', [
                    'status' => $response->status(),
                    'body'   => mb_substr($response->body(), 0, 300),
                ]);
                return null;
            }

            $data = $response->json();
            $this->recordGroqUsage(
                $data['usage']['prompt_tokens'] ?? 0,
                $data['usage']['completion_tokens'] ?? 0,
                'text'
            );
            $this->recordModelUsage(
                'groq', $this->groqModel, 'text',
                $data['usage']['prompt_tokens'] ?? 0,
                $data['usage']['completion_tokens'] ?? 0
            );

            return $data['choices'][0]['message']['content'] ?? null;

        } catch (\Exception $e) {
            Log::error('GeminiService::callGroq exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Call Groq vision model as a fallback for image analysis.
     * Returns null if no API key is configured or the call fails.
     */
    private function callGroqVision(string $base64Image, string $mimeType, string $prompt): ?string
    {
        return $this->callGroqVisionMulti(
            [['mime_type' => $mimeType, 'data' => $base64Image]],
            $prompt
        );
    }

    /**
     * Groq vision multi-image fallback. OpenAI-compatible chat completion
     * mendukung multiple 'image_url' content parts. Vision model di Groq
     * mendukung beberapa image per request (terkadang dibatasi 5).
     */
    private function callGroqVisionMulti(array $images, string $prompt): ?string
    {
        if (empty($this->groqApiKey) || empty($images)) {
            return null;
        }

        try {
            $content = [['type' => 'text', 'text' => $prompt]];
            foreach ($images as $img) {
                $mime = $img['mime_type'] ?? 'image/jpeg';
                $b64  = $img['data'] ?? '';
                if ($b64 === '') continue;
                $content[] = ['type' => 'image_url', 'image_url' => [
                    'url' => "data:{$mime};base64,{$b64}",
                ]];
            }
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(45)->post($this->groqEndpoint, [
                'model'    => $this->groqVisionModel,
                'messages' => [[
                    'role'    => 'user',
                    'content' => $content,
                ]],
                'temperature' => 0.1,
                'max_tokens'  => 2048,
            ]);

            if ($response->failed()) {
                Log::error('GeminiService::callGroqVision failed', [
                    'status' => $response->status(),
                    'body'   => mb_substr($response->body(), 0, 300),
                ]);
                return null;
            }

            $data = $response->json();
            $this->recordGroqUsage(
                $data['usage']['prompt_tokens'] ?? 0,
                $data['usage']['completion_tokens'] ?? 0,
                'image'
            );
            $this->recordModelUsage(
                'groq', $this->groqVisionModel, 'image',
                $data['usage']['prompt_tokens'] ?? 0,
                $data['usage']['completion_tokens'] ?? 0
            );

            return $data['choices'][0]['message']['content'] ?? null;

        } catch (\Exception $e) {
            Log::error('GeminiService::callGroqVision exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Track Groq usage in a separate daily cache key so it doesn't pollute
     * Gemini token accounting on the AI Health dashboard.
     */
    private function recordGroqUsage(int $prompt, int $output, string $type = 'text'): void
    {
        $key     = 'groq_usage_' . now()->toDateString();
        $current = Cache::get($key, [
            'calls'         => 0,
            'image_calls'   => 0,
            'prompt_tokens' => 0,
            'output_tokens' => 0,
        ]);
        $current['calls']++;
        if ($type === 'image') $current['image_calls']++;
        $current['prompt_tokens'] += $prompt;
        $current['output_tokens'] += $output;
        Cache::put($key, $current, now()->addDays(8));
    }

    // ── Circuit breaker ───────────────────────────────────────────────────────

    /**
     * Returns true when the circuit is OPEN (too many recent failures).
     */
    public function isCircuitOpen(): bool
    {
        $openUntil = Cache::get(self::CB_OPEN_UNTIL_KEY);
        if ($openUntil && now()->timestamp < $openUntil) {
            return true;
        }
        return false;
    }

    /** Record a failed API call; open the circuit if threshold reached. */
    private function recordFailure(): void
    {
        $failures = (int) Cache::get(self::CB_FAILURES_KEY, 0) + 1;
        Cache::put(self::CB_FAILURES_KEY, $failures, now()->addMinutes(10));

        if ($failures >= self::CB_THRESHOLD) {
            $until = now()->addSeconds(self::CB_TIMEOUT_SEC)->timestamp;
            Cache::put(self::CB_OPEN_UNTIL_KEY, $until, now()->addSeconds(self::CB_TIMEOUT_SEC + 60));
            Log::error('GeminiService: circuit breaker OPENED after ' . $failures . ' failures. Pausing for ' . self::CB_TIMEOUT_SEC . 's.');
        }
    }

    /** Reset failure counter after a successful call. */
    private function resetCircuit(): void
    {
        Cache::forget(self::CB_FAILURES_KEY);
        Cache::forget(self::CB_OPEN_UNTIL_KEY);
    }

    // ── Token tracking ────────────────────────────────────────────────────────

    /**
     * Accumulate token usage into a daily cache entry.
     * Key: gemini_usage_YYYY-MM-DD  →  {calls, image_calls, prompt_tokens, output_tokens}
     */
    private function recordTokenUsage(int $prompt, int $output, string $type = 'text'): void
    {
        $key     = 'gemini_usage_' . now()->toDateString();
        $current = Cache::get($key, [
            'calls'         => 0,
            'image_calls'   => 0,
            'prompt_tokens' => 0,
            'output_tokens' => 0,
        ]);
        $current['calls']++;
        if ($type === 'image') $current['image_calls']++;
        $current['prompt_tokens'] += $prompt;
        $current['output_tokens'] += $output;
        Cache::put($key, $current, now()->addDays(8));
    }

    /**
     * Per-model daily breakdown so the AI Health page can show which model
     * (Gemini text, Gemini vision, Groq text fallback, Groq vision fallback)
     * is driving cost. Key: ai_model_usage_YYYY-MM-DD → array keyed by
     * "<provider>|<model>|<type>" → {provider, model, type, calls, prompt_tokens, output_tokens}
     */
    private function recordModelUsage(string $provider, string $model, string $type, int $prompt, int $output): void
    {
        $key       = 'ai_model_usage_' . now()->toDateString();
        $current   = Cache::get($key, []);
        $bucketKey = "{$provider}|{$model}|{$type}";
        if (!isset($current[$bucketKey])) {
            $current[$bucketKey] = [
                'provider'      => $provider,
                'model'         => $model,
                'type'          => $type,
                'calls'         => 0,
                'prompt_tokens' => 0,
                'output_tokens' => 0,
            ];
        }
        $current[$bucketKey]['calls']++;
        $current[$bucketKey]['prompt_tokens'] += $prompt;
        $current[$bucketKey]['output_tokens'] += $output;
        Cache::put($key, $current, now()->addDays(8));
    }
}
