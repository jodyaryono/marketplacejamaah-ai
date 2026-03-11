<?php

namespace App\Agents;

use App\Models\AgentLog;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Setting;
use App\Services\GeminiService;

class MessageModerationAgent
{
    public function __construct(
        private GeminiService $gemini
    ) {}

    public function handle(Message $message): array
    {
        $start = microtime(true);
        $log = AgentLog::create([
            'agent_name' => 'MessageModerationAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            // If already classified as an ad, do a quick scam-indicator check first.
            // Legitimate ads skip full moderation. Suspected scam/spam-ads fall through.
            if ($message->is_ad) {
                $lower = strtolower($message->raw_body ?? '');
                $scamPhrases = [
                    'tanpa kerja',
                    'tanpa modal',
                    'penghasilan per hari',
                    'juta per hari',
                    'passive income',
                    'robot trading',
                    'binary',
                    'mlm',
                    'downline',
                    'upline',
                    'klik link',
                    'join sekarang',
                    'daftar sekarang',
                    'investasi modal kecil',
                    'bit.ly',
                    't.me/',
                    'wd mudah',
                    'profit harian',
                    'bocoran',
                    'penipuan',
                ];
                $likelyScam = false;
                foreach ($scamPhrases as $phrase) {
                    if (str_contains($lower, $phrase)) {
                        $likelyScam = true;
                        break;
                    }
                }

                if (!$likelyScam) {
                    $result = [
                        'category' => 'ad',
                        'is_violation' => false,
                        'violation_severity' => null,
                        'violation_reason' => null,
                        'reply_group_text' => null,
                        'reply_dm_text' => null,
                        'language_tone' => 'formal',
                    ];
                    $message->update([
                        'message_category' => 'ad',
                        'violation_detected' => false,
                        'moderation_result' => $result,
                    ]);
                    $log->update(['status' => 'skipped', 'output_payload' => $result]);
                    return $result;
                }
                // Suspected scam-ad → fall through to full Gemini moderation
            }

            $text = $message->raw_body ?? '';
            $senderName = $message->sender_name ?? $message->sender_number;

            $promptTemplate = Setting::get('prompt_moderation', 'Moderasi pesan dari {senderName}: {text}');
            $prompt = str_replace(['{senderName}', '{text}'], [$senderName, $text], $promptTemplate);

            $result = $this->gemini->generateJson($prompt);

            if (!$result) {
                $result = $this->fallbackModeration($text, $senderName);
            }

            $isViolation = (bool) ($result['is_violation'] ?? false);
            $category = $result['category'] ?? 'unknown';

            $message->update([
                'message_category' => $category,
                'violation_detected' => $isViolation,
                'moderation_result' => $result,
            ]);

            if ($isViolation) {
                $contact = Contact::where('phone_number', $message->sender_number)->first();
                if ($contact) {
                    // Auto-reset warning_count if last warning is older than configured days
                    $resetDays = (int) Setting::get('warning_reset_days', 1);
                    if ($contact->warning_count > 0 && $contact->last_warning_at && $contact->last_warning_at->lt(now()->subDays($resetDays))) {
                        $contact->update(['warning_count' => 0, 'is_blocked' => false]);
                    }

                    $contact->increment('warning_count');
                    $contact->increment('total_violations');
                    $contact->update(['last_warning_at' => now()]);
                }
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
            return [
                'category' => 'unknown',
                'is_violation' => false,
                'violation_severity' => null,
                'violation_reason' => null,
                'reply_group_text' => null,
                'reply_dm_text' => null,
                'language_tone' => 'formal',
            ];
        }
    }

    private function fallbackModeration(string $text, string $senderName): array
    {
        // Regex-based fallback ketika Gemini tidak tersedia
        $insultPattern = '/\b(?:anjing|babi|bangsat|bajingan|kontol|memek|goblok|tolol|idiot|tai|sialan|keparat|brengsek|celeng|laknat|kampret|kurang ajar)\b/i';
        $spamPattern = '/\b(?:join|gabung|daftar sekarang|klik link|bit\.ly|t\.me|sebarkan|forward pesan|wd mudah|profit|passive income|mlm|downline|upline|binary|robot trading|investasi modal kecil|tanpa kerja|tanpa modal|penghasilan \d+|juta per hari|profit harian)\b/i';

        if (preg_match($insultPattern, $text)) {
            return [
                'category' => 'insult',
                'is_violation' => true,
                'violation_severity' => 'high',
                'violation_reason' => 'Mengandung kata kasar atau hinaan',
                'reply_group_text' => null,
                'reply_dm_text' => "Halo {$senderName}, kami mendeteksi bahwa pesanmu mengandung kata kasar yang melanggar aturan grup. Mohon jaga sopan santun. Pelanggaran berulang dapat mengakibatkan kamu dikeluarkan dari grup.",
                'language_tone' => 'formal',
            ];
        }

        if (preg_match($spamPattern, $text)) {
            return [
                'category' => 'spam',
                'is_violation' => true,
                'violation_severity' => 'medium',
                'violation_reason' => 'Terindikasi spam atau promosi tidak relevan',
                'reply_group_text' => null,
                'reply_dm_text' => "Halo {$senderName}, pesan yang kamu kirim terindikasi spam atau promosi di luar marketplace. Mohon gunakan grup hanya untuk aktivitas jual-beli produk yang sesuai.",
                'language_tone' => 'formal',
            ];
        }

        return [
            'category' => 'unknown',
            'is_violation' => false,
            'violation_severity' => null,
            'violation_reason' => null,
            'reply_group_text' => null,
            'reply_dm_text' => null,
            'language_tone' => 'informal',
        ];
    }
}
