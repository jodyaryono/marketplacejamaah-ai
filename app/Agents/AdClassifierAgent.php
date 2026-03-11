<?php

namespace App\Agents;

use App\Models\AgentLog;
use App\Models\Message;
use App\Models\Setting;
use App\Services\GeminiService;

class AdClassifierAgent
{
    public function __construct(
        private GeminiService $gemini
    ) {}

    public function handle(Message $message, array $parsed): array
    {
        $start = microtime(true);
        $log = AgentLog::create([
            'agent_name' => 'AdClassifierAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            $text = $parsed['text_content'] ?? $message->raw_body ?? '';

            // One-liner guard: skip stickers, emoji-only, very short messages
            $wordCount = $parsed['word_count'] ?? str_word_count($text);
            if (strlen($text) < 10 || $wordCount < 4) {
                $result = ['is_ad' => false, 'confidence' => 0.0, 'reason' => 'one_liner_guard', 'word_count' => $wordCount];
                $message->update(['is_ad' => false, 'ad_confidence' => 0.0, 'is_processed' => true]);
                $log->update(['status' => 'success', 'output_payload' => $result]);
                return $result;
            }

            $promptTemplate = Setting::get('prompt_ad_classifier', 'Klasifikasi iklan: {text}');
            $prompt = str_replace('{text}', $text, $promptTemplate);

            $result = $this->gemini->generateJson($prompt);

            if (!$result) {
                // Fall back to heuristic
                $hasAdKeywords = (bool) preg_match('/\b(?:jual|dijual|WTS|available|ready\s*stock|harga|rp\.?\s*\d|cod|dp\s*\d|terjual|penawaran|promo|diskon|order)\b/i', $text);
                $result = [
                    'is_ad' => $hasAdKeywords || ($parsed['has_price'] ?? false),
                    'confidence' => $hasAdKeywords ? 0.7 : ($parsed['has_price'] ? 0.5 : 0.1),
                    'reason' => 'heuristic_fallback',
                ];
            }

            $isAd = (bool) ($result['is_ad'] ?? false);
            $confidence = (float) ($result['confidence'] ?? 0.0);

            $message->update([
                'is_ad' => $isAd,
                'ad_confidence' => $confidence,
                'is_processed' => true,
                'processed_at' => now(),
            ]);

            if ($isAd) {
                $message->group?->increment('ad_count');
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            $log->update([
                'status' => 'success',
                'output_payload' => $result,
                'duration_ms' => $duration,
            ]);

            return $result;
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return ['is_ad' => false, 'confidence' => 0.0, 'reason' => 'error'];
        }
    }
}
