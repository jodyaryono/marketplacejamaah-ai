<?php

namespace App\Agents;

use App\Models\AgentLog;
use App\Models\Message;
use App\Models\Setting;

class MessageParserAgent
{
    public function handle(Message $message): array
    {
        $start = microtime(true);
        $log = AgentLog::create([
            'agent_name' => 'MessageParserAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            $parsed = [
                'type' => $message->message_type,
                'text_content' => null,
                'has_media' => false,
                'media_url' => null,
                'has_price' => false,
                'has_contact' => false,
                'word_count' => 0,
            ];

            // Parse text
            if ($message->raw_body) {
                $text = trim($message->raw_body);
                $parsed['text_content'] = $text;
                $parsed['word_count'] = str_word_count($text);

                // Detect price pattern (configurable regex)
                $pricePattern = Setting::get('config_price_regex', '(?:rp\.?\s*[\d.,]+|harga\s*[\d.,]+|[\d.,]+\s*(?:ribu|rb|juta|k))');
                $parsed['has_price'] = (bool) preg_match('/' . $pricePattern . '/i', $text);

                // Detect contact/phone number (configurable regex)
                $contactPattern = Setting::get('config_contact_regex', '(?:wa|whatsapp|hub|telp|hp|contact|call)?\s*(?::|\s)?\s*(?:\+?62|0)[0-9\s\-]{8,14}');
                $parsed['has_contact'] = (bool) preg_match('/' . $contactPattern . '/i', $text);
            }

            // Media
            if ($message->media_url || $message->message_type !== 'text') {
                $parsed['has_media'] = true;
                $parsed['media_url'] = $message->media_url;
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            $log->update([
                'status' => 'success',
                'output_payload' => $parsed,
                'duration_ms' => $duration,
            ]);

            return $parsed;
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return [];
        }
    }
}
