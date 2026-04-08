<?php

namespace App\Services;

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
        $this->apiKey   = config('services.gemini.api_key');
        $this->model    = config('services.gemini.model', 'gemini-flash-latest');
        $this->endpoint = config('services.gemini.endpoint');

        $this->groqApiKey      = config('services.groq.api_key');
        $this->groqModel       = config('services.groq.model', 'llama-3.3-70b-versatile');
        $this->groqVisionModel = config('services.groq.vision_model', 'meta-llama/llama-4-scout-17b-16e-instruct');
        $this->groqEndpoint    = config('services.groq.endpoint', 'https://api.groq.com/openai/v1/chat/completions');
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

        $text    = preg_replace('/```json\s*/i', '', $text);
        $text    = preg_replace('/```\s*/i', '', $text);
        $text    = trim($text);
        $decoded = json_decode($text, true);

        if (!is_array($decoded)) {
            Log::warning('GeminiService::generateJson: non-JSON response', [
                'raw' => mb_substr($text, 0, 300),
            ]);
            return null;
        }
        return $decoded;
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
        if (empty($this->groqApiKey)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post($this->groqEndpoint, [
                'model'    => $this->groqVisionModel,
                'messages' => [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => [
                            'url' => "data:{$mimeType};base64,{$base64Image}",
                        ]],
                    ],
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
}
