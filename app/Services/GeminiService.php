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

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-flash-latest');
        $this->endpoint = config('services.gemini.endpoint');
    }

    public function generateContent(string $prompt): ?string
    {
        $url = "{$this->endpoint}/{$this->model}:generateContent";
        $body = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 2048,
            ],
        ];

        // Retry up to 3 times on 429 rate-limit with short delay (let queue handle longer backoff)
        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'X-goog-api-key' => $this->apiKey,
                ])->timeout(30)->post($url, $body);

                if ($response->status() === 429) {
                    Log::warning("GeminiService: rate limited (attempt {$attempt}/{$maxAttempts})", [
                        'status' => 429,
                    ]);
                    if ($attempt < $maxAttempts) {
                        sleep(5);  // short pause, then retry
                        continue;
                    }
                    Log::error('GeminiService::generateContent failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                if ($response->failed()) {
                    Log::error('GeminiService::generateContent failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                $data = $response->json();

                // Track token usage in daily cache aggregate
                $usage = $data['usageMetadata'] ?? [];
                $this->recordTokenUsage(
                    $usage['promptTokenCount'] ?? 0,
                    $usage['candidatesTokenCount'] ?? 0,
                    'text'
                );

                return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            } catch (\Exception $e) {
                Log::error('GeminiService::generateContent exception', ['error' => $e->getMessage()]);
                return null;
            }
        }

        return null;
    }

    public function generateJson(string $prompt): ?array
    {
        $text = $this->generateContent($prompt . "\n\nRespond ONLY with valid JSON, no markdown, no explanation.");
        if (!$text)
            return null;

        // Strip markdown code blocks if present
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*/i', '', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function analyzeImageWithText(string $base64Image, string $mimeType, string $prompt): ?string
    {
        try {
            $url = "{$this->endpoint}/{$this->model}:generateContent";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-goog-api-key' => $this->apiKey,
            ])->timeout(30)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Image,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            if ($response->failed()) {
                return null;
            }

            $data = $response->json();

            // Track token usage
            $usage = $data['usageMetadata'] ?? [];
            $this->recordTokenUsage(
                $usage['promptTokenCount'] ?? 0,
                $usage['candidatesTokenCount'] ?? 0,
                'image'
            );

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (\Exception $e) {
            Log::error('GeminiService::analyzeImageWithText exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Accumulate token usage into a daily cache entry.
     * Key: gemini_usage_YYYY-MM-DD  →  {calls, image_calls, prompt_tokens, output_tokens}
     */
    private function recordTokenUsage(int $prompt, int $output, string $type = 'text'): void
    {
        $key = 'gemini_usage_' . now()->toDateString();
        $current = Cache::get($key, [
            'calls' => 0,
            'image_calls' => 0,
            'prompt_tokens' => 0,
            'output_tokens' => 0,
        ]);
        $current['calls']++;
        if ($type === 'image') {
            $current['image_calls']++;
        }
        $current['prompt_tokens'] += $prompt;
        $current['output_tokens'] += $output;
        // Keep for 8 days so we always have a full 7-day window
        Cache::put($key, $current, now()->addDays(8));
    }
}
