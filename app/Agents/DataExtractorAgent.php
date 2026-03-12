<?php

namespace App\Agents;

use App\Models\AgentLog;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Setting;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DataExtractorAgent
{
    public function __construct(
        private GeminiService $gemini
    ) {}

    public function handle(Message $message): ?Listing
    {
        $start = microtime(true);
        $log = AgentLog::create([
            'agent_name' => 'DataExtractorAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            $text = $message->raw_body ?? '';

            // Fetch content from external URLs found in the message
            $externalContent = '';
            if (preg_match_all('/https?:\/\/[^\s\]>"\')]+/i', $text, $urlMatches)) {
                foreach (array_slice($urlMatches[0], 0, 2) as $url) {
                    try {
                        // SSRF prevention: block internal/private IP ranges
                        $host = parse_url($url, PHP_URL_HOST);
                        if ($host) {
                            $ip = gethostbyname($host);
                            if ($ip && (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
                                continue; // skip internal/private IPs
                            }
                        }
                        $client = new \GuzzleHttp\Client([
                            'timeout' => 8,
                            'connect_timeout' => 5,
                            'allow_redirects' => ['max' => 3],
                        ]);
                        $response = $client->get($url, [
                            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; MarketplaceBot/1.0)'],
                        ]);
                        $html = (string) $response->getBody();
                        $plain = strip_tags($html);
                        $plain = preg_replace('/\s+/', ' ', $plain);
                        $plain = trim($plain);
                        if (strlen($plain) > 50) {
                            $externalContent .= "\n\nKonten dari link {$url}:\n" . \Illuminate\Support\Str::limit($plain, 1500);
                        }
                    } catch (\Exception $e) {
                        // Link tidak bisa diakses — lanjutkan tanpa konten URL
                    }
                }
            }

            $categories = Category::pluck('name')->implode(', ');

            $promptTemplate = Setting::get('prompt_data_extractor', 'Ekstrak data iklan: {text}{externalContent} Kategori: {categories}');
            $prompt = str_replace(['{text}', '{externalContent}', '{categories}'], [$text, $externalContent, $categories], $promptTemplate);

            $extracted = $this->gemini->generateJson($prompt);

            // Gemini failed (rate limit, timeout, etc.) — create a basic listing from raw message data
            if (!$extracted) {
                $senderContact = $message->sender_number
                    ? Contact::where('phone_number', $message->sender_number)->first()
                    : null;
                if ($senderContact) {
                    $senderContact->increment('ad_count');
                }

                $mediaUrls = [];
                if ($message->media_url) {
                    $mediaUrls[] = $message->media_url;
                }

                $listing = Listing::updateOrCreate(
                    ['message_id' => $message->id],
                    [
                        'whatsapp_group_id' => $message->whatsapp_group_id,
                        'contact_id' => $senderContact?->id,
                        'title' => Str::limit($text, 80),
                        'description' => $text,
                        'media_urls' => $mediaUrls,
                        'status' => 'active',
                        'source_date' => $message->sent_at ?? $message->created_at,
                    ]
                );

                $log->update(['status' => 'success', 'output_payload' => ['gemini_fallback' => true], 'duration_ms' => (int) ((microtime(true) - $start) * 1000)]);
                return $listing;
            }

            // Resolve category
            $categoryId = null;
            if (!empty($extracted['category'])) {
                $category = Category::where('name', $extracted['category'])
                    ->orWhere('slug', Str::slug($extracted['category']))
                    ->first();
                $categoryId = $category?->id;
                if ($category) {
                    $category->increment('listing_count');
                }
            }

            // Resolve contact from extracted number, or fall back to message sender
            $contactId = null;
            if (!empty($extracted['contact_number'])) {
                $contact = Contact::firstOrCreate(
                    ['phone_number' => $extracted['contact_number']],
                    ['name' => $extracted['contact_name'] ?? null]
                );
                $contact->increment('ad_count');
                $contactId = $contact->id;
            } elseif ($message->sender_number) {
                $senderContact = Contact::where('phone_number', $message->sender_number)->first();
                if ($senderContact) {
                    $senderContact->increment('ad_count');
                    $contactId = $senderContact->id;
                }
            }

            // Build media_urls: only actual image/media URLs (never text links)
            $rawExtracted = $extracted['media_urls'] ?? [];
            $mediaUrls = array_values(array_filter($rawExtracted, function (string $url): bool {
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    return false;
                }
                $lower = strtolower($url);
                // Block known non-media domains
                $blocked = [
                    'forms.gle',
                    'chat.whatsapp.com',
                    'wa.me',
                    'bit.ly',
                    'tinyurl.com',
                    'maps.google.com',
                    'goo.gl',
                    'linktr.ee',
                    'tokopedia.com',
                    'shopee.co.id',
                    'youtube.com',
                    'youtu.be',
                    'instagram.com',
                    'facebook.com',
                    'twitter.com',
                    'tiktok.com',
                ];
                foreach ($blocked as $b) {
                    if (str_contains($lower, $b)) {
                        return false;
                    }
                }
                // Only allow known media CDNs or image file extensions
                $mediaCdns = [
                    'integrasi-wa.jodyaryono.id/uploads',
                    'marketplacejamaah-ai.jodyaryono.id/uploads',
                    'mmg.whatsapp.net',
                    'pps.whatsapp.net',
                ];
                foreach ($mediaCdns as $cdn) {
                    if (str_contains($lower, $cdn)) {
                        return true;
                    }
                }
                $path = parse_url($lower, PHP_URL_PATH) ?? '';
                foreach (['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov'] as $ext) {
                    if (str_ends_with($path, '.' . $ext)) {
                        return true;
                    }
                }
                return false;
            }));
            if ($message->media_url && !in_array($message->media_url, $mediaUrls)) {
                $mediaUrls[] = $message->media_url;
            }

            // Smart duplicate detection: same user, same group, similar product title within 30 days.
            // Uses PHP similar_text() to catch re-posts with slightly different wording (typos,
            // updated prices, etc.). If a match (≥60%) is found: delete the old listing so the
            // fresh new one takes its place, and DM the user to confirm the update.
            $deletedOldListing = null;
            $newTitle = $extracted['title'] ?? Str::limit($text, 80);
            if ($message->whatsapp_group_id && $message->sender_number && $newTitle) {
                $candidateListings = Listing::where('whatsapp_group_id', $message->whatsapp_group_id)
                    ->where('status', 'active')
                    ->whereHas('contact', fn($q) => $q->where('phone_number', $message->sender_number))
                    ->where('message_id', '!=', $message->id)
                    ->where('source_date', '>=', now()->subDays(30))
                    ->orderByDesc('source_date')
                    ->limit(20)
                    ->get();

                foreach ($candidateListings as $candidate) {
                    $sim = 0;
                    similar_text(strtolower($newTitle), strtolower($candidate->title ?? ''), $sim);
                    if ($sim >= 60) {
                        $deletedOldListing = $candidate;
                        $candidate->delete();
                        Log::info("DataExtractorAgent: deleted old similar listing #{$candidate->id} ('{$candidate->title}') — replaced by new from message #{$message->id} (similarity={$sim}%)");
                        break;
                    }
                }
            }

            // Notify user when their old listing was replaced by the new re-post
            if ($deletedOldListing && $message->sender_number) {
                try {
                    $wa = app(\App\Services\WhacenterService::class);
                    $name = $message->sender_name ?? $message->sender_number;
                    $wa->sendMessage(
                        $message->sender_number,
                        "🔄 *Iklan Lama Dihapus & Diganti*\n\n"
                            . "Halo *{$name}*! 👋\n\n"
                            . "Kami mendeteksi kamu memposting produk yang sama:\n"
                            . "📦 *\"{$deletedOldListing->title}\"*\n\n"
                            . 'Iklan lama sudah otomatis dihapus. '
                            . "Iklan barumu sedang diproses dan akan segera tayang. ⏳\n\n"
                            . '_Kamu akan mendapat notifikasi begitu iklan barumu aktif._ 🙏'
                    );
                } catch (\Throwable $e) {
                    Log::warning('DataExtractorAgent: update-replace DM failed', ['error' => $e->getMessage()]);
                }
            }

            // Exact-body dedup safety net: guards against rare race conditions where two
            // messages with identical content both pass the message-level dedup above.
            if (!$deletedOldListing && $message->whatsapp_group_id && $message->sender_number) {
                $existing = Listing::where('whatsapp_group_id', $message->whatsapp_group_id)
                    ->whereHas('message', fn($q) => $q
                        ->where('sender_number', $message->sender_number)
                        ->whereRaw('md5(raw_body) = md5(?)', [$message->raw_body ?? ''])
                        ->where('id', '!=', $message->id)
                        ->where('created_at', '>=', now()->subDay()))
                    ->first();

                if ($existing) {
                    $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'duplicate_listing', 'existing_listing_id' => $existing->id]]);
                    return $existing;
                }
            }

            // Resolve contact_number: prefer AI-extracted, then sender, then contact's phone
            $contactNumber = $extracted['contact_number']
                ?? (preg_match('/^62\d{8,13}$/', $message->sender_number ?? '') ? $message->sender_number : null);
            if (!$contactNumber && $contactId) {
                $fallbackPhone = Contact::find($contactId)?->phone_number;
                if ($fallbackPhone && preg_match('/^62\d{8,13}$/', $fallbackPhone)) {
                    $contactNumber = $fallbackPhone;
                }
            }

            $listing = Listing::updateOrCreate(
                ['message_id' => $message->id],
                [
                    'whatsapp_group_id' => $message->whatsapp_group_id,
                    'category_id' => $categoryId,
                    'contact_id' => $contactId,
                    'title' => $extracted['title'] ?? Str::limit($text, 80),
                    'description' => $text,  // Always use original raw text — never Gemini summary
                    'price' => $extracted['price'],
                    'price_min' => $extracted['price_min'],
                    'price_max' => $extracted['price_max'],
                    'price_label' => $extracted['price_label'],
                    'contact_number' => $contactNumber,
                    'contact_name' => $extracted['contact_name'],
                    'location' => $extracted['location'],
                    'condition' => $extracted['condition'] ?? 'unknown',
                    'media_urls' => $mediaUrls,
                    'status' => 'active',
                    'source_date' => $message->sent_at ?? $message->created_at,
                ]
            );

            $duration = (int) ((microtime(true) - $start) * 1000);
            $log->update([
                'status' => 'success',
                'output_payload' => $extracted,
                'duration_ms' => $duration,
            ]);

            return $listing;
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return null;
        }
    }
}
