<?php

namespace App\Agents;

use App\Models\AgentLog;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Setting;
use App\Services\GeminiService;
use App\Services\WhacenterService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImageAnalyzerAgent
{
    public function __construct(
        private GeminiService $gemini,
        private WhacenterService $whacenter,
    ) {}

    public function handle(Message $message, Listing $listing): array
    {
        $start = microtime(true);
        $log = AgentLog::create([
            'agent_name' => 'ImageAnalyzerAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            $mediaUrl = $message->media_url;

            if (!$mediaUrl || $message->message_type === 'text') {
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'no_media']]);
                return [];
            }

            // Download image
            $imageData = Http::timeout(15)->get($mediaUrl);
            if ($imageData->failed()) {
                $log->update(['status' => 'failed', 'error' => 'Cannot download image']);
                return [];
            }

            $base64 = base64_encode($imageData->body());
            $mimeType = $imageData->header('Content-Type') ?: 'image/jpeg';

            $categories = Category::pluck('name')->implode(', ');

            $currentTitle = $listing->title ?? '';
            $currentPrice = $listing->price ?? null;
            $currentDesc = $listing->description ?? '';

            $promptTemplate = Setting::get('prompt_image_enrichment', 'Analisa gambar iklan. Judul: {currentTitle}, Harga: {currentPrice}. Kategori: {categories}');
            $prompt = str_replace(['{currentTitle}', '{currentPrice}', '{categories}'], [$currentTitle, $currentPrice, $categories], $promptTemplate);

            $result = $this->gemini->analyzeImageWithText($base64, $mimeType, $prompt);
            $parsed = [];
            if ($result) {
                $clean = preg_replace('/```json\s*/i', '', $result);
                $clean = preg_replace('/```\s*/i', '', $clean);
                $parsed = json_decode(trim($clean), true) ?? ['raw_analysis' => $result];
            }

            $listingUpdates = [];

            // Update description with image insights
            if (!empty($parsed['product_description'])) {
                $imageInsight = "\n\n" . $parsed['product_description'];
                if (!str_contains($currentDesc, $parsed['product_description'])) {
                    $listingUpdates['description'] = $currentDesc . $imageInsight;
                }
            }

            // Fix title when image gives a better one (current title is generic/empty)
            if (!empty($parsed['title'])) {
                $genericTitles = ['pemesanan', 'tanya', 'japri', 'order', 'info', 'produk dari gambar'];
                $currentLower = strtolower($currentTitle);
                $isGeneric = !$currentTitle ||
                    Str::length($currentTitle) < 5 ||
                    collect($genericTitles)->contains(fn($g) => str_contains($currentLower, $g));
                if ($isGeneric) {
                    $listingUpdates['title'] = $parsed['title'];
                }
            }

            // Fix price when image reveals it and listing has none
            if (!empty($parsed['price']) && !$listing->price) {
                $listingUpdates['price'] = $parsed['price'];
            }

            // Fix category when image reveals a better match
            $categoryWasCorrected = false;
            $newCategoryName = null;
            if (!empty($parsed['category'])) {
                $matchedCat = Category::where('name', $parsed['category'])
                    ->orWhere('slug', Str::slug($parsed['category']))
                    ->first();
                if ($matchedCat) {
                    $oldCat = $listing->category;
                    $isLainnya = !$oldCat || strtolower($oldCat->name ?? '') === 'lainnya';
                    if ($isLainnya && $matchedCat->slug !== 'lainnya') {
                        $listingUpdates['category_id'] = $matchedCat->id;
                        $categoryWasCorrected = true;
                        $newCategoryName = $matchedCat->name;
                    }
                }
            }

            if ($listingUpdates) {
                $listing->update($listingUpdates);
            }

            // Notify seller via DM when category was silently corrected by AI
            if ($categoryWasCorrected) {
                $this->sendCategoryCorrectedDm($message, $listing, $newCategoryName);
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            $log->update([
                'status' => 'success',
                'output_payload' => $parsed,
                'duration_ms' => $duration,
            ]);

            return $parsed;
        } catch (\Exception $e) {
            Log::error('ImageAnalyzerAgent failed', ['error' => $e->getMessage()]);
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Notify the seller via DM that the AI has corrected the listing category from the image.
     */
    private function sendCategoryCorrectedDm(Message $message, Listing $listing, string $newCategory): void
    {
        try {
            $contact = Contact::where('phone_number', $message->sender_number)->first();
            $name = $contact ? $contact->getSapaan($message->sender_name) : ($message->sender_name ?: 'Kak');
            $title = $listing->title ?? 'iklanmu';
            $listingUrl = config('app.url') . '/listings/' . $listing->id;

            $text = "📋 *Info Otomatis dari AI Marketplace Jamaah*\n\n"
                . "Halo *{$name}*! 👋\n\n"
                . "AI kami menganalisa gambar yang kamu kirim untuk iklan *\"{$title}\"* "
                . "dan mendeteksi kategori yang lebih tepat.\n\n"
                . "✅ *Kategori diperbarui menjadi:* {$newCategory}\n\n"
                . "Kamu bisa cek & ubah iklanmu di sini:\n{$listingUrl}\n\n"
                . '_Jika ada yang kurang tepat, silakan hubungi admin._';

            $this->whacenter->sendMessage($message->sender_number, $text);
        } catch (\Exception $e) {
            Log::warning('ImageAnalyzerAgent: failed to send category-corrected DM', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Analyze an image-only message to detect if it's an advertisement.
     * Returns null if not an ad, or a structured listing data array if it is.
     */
    public function detectAdFromImage(Message $message): ?array
    {
        $start = microtime(true);
        $log = AgentLog::create([
            'agent_name' => 'ImageAnalyzerAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            $mediaUrl = $message->media_url;
            if (!$mediaUrl) {
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'no_media']]);
                return null;
            }

            $imageData = Http::timeout(15)->get($mediaUrl);
            if ($imageData->failed()) {
                $log->update(['status' => 'failed', 'error' => 'Cannot download image']);
                return null;
            }

            $base64 = base64_encode($imageData->body());
            $mimeType = $imageData->header('Content-Type') ?: 'image/jpeg';

            $categories = \App\Models\Category::pluck('name')->implode(', ');

            $promptTemplate = Setting::get('prompt_image_ad_detection', 'Deteksi iklan dari gambar. Kategori: {categories}');
            $prompt = str_replace('{categories}', $categories, $promptTemplate);

            $result = $this->gemini->analyzeImageWithText($base64, $mimeType, $prompt);

            if (!$result) {
                $log->update(['status' => 'failed', 'error' => 'Gemini returned null']);
                return null;
            }

            $clean = preg_replace('/```json\s*/i', '', $result);
            $clean = preg_replace('/```\s*/i', '', $clean);
            $parsed = json_decode(trim($clean), true);

            if (!$parsed || !isset($parsed['is_ad'])) {
                $log->update(['status' => 'failed', 'error' => 'Invalid JSON from Gemini', 'output_payload' => ['raw' => $result]]);
                return null;
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            $log->update([
                'status' => 'success',
                'output_payload' => $parsed,
                'duration_ms' => $duration,
            ]);

            // Always return the full parsed data; callers read is_ad + needs_clarification
            return $parsed;
        } catch (\Exception $e) {
            Log::error('ImageAnalyzerAgent::detectAdFromImage failed', ['error' => $e->getMessage()]);
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return null;
        }
    }
}
