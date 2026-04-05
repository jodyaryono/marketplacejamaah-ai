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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BotQueryAgent
{
    private string $baseUrl;
    private AdBuilderAgent $adBuilder;

    public function __construct(
        private WhacenterService $whacenter,
        private GeminiService $gemini,
    ) {
        $this->baseUrl = rtrim(config('app.url'), '/');
        $this->adBuilder = app(AdBuilderAgent::class);
    }

    /**
     * Handle a DM query from a registered member.
     * Returns true if message was handled as a query, false otherwise.
     */
    public function handle(Message $message): bool
    {
        $log = AgentLog::create([
            'agent_name' => 'BotQueryAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            // ── Handle shared location FIRST (no text needed) ─────────────────
            if ($message->message_type === 'location') {
                $locPayload = $message->raw_payload ?? [];
                $lat = isset($locPayload['location']['latitude'])
                    ? (float) $locPayload['location']['latitude']
                    : (isset($locPayload['latitude']) ? (float) $locPayload['latitude'] : null);
                $lng = isset($locPayload['location']['longitude'])
                    ? (float) $locPayload['location']['longitude']
                    : (isset($locPayload['longitude']) ? (float) $locPayload['longitude'] : null);

                if ($lat !== null && $lng !== null) {
                    $cacheKey = 'loc_pending:' . $message->sender_number;
                    Cache::put($cacheKey, ['lat' => $lat, 'lng' => $lng], now()->addMinutes(5));

                    $reply = "📍 *Lokasi kamu sudah diterima!*\n\n"
                        . "Mau digunakan untuk apa?\n\n"
                        . "1️⃣ *Update lokasi bisnis* saya\n"
                        . "2️⃣ *Cari produk* di sekitar lokasi ini\n\n"
                        . '_Balas dengan angka *1* atau *2*_';

                    $this->whacenter->sendMessage($message->sender_number, $reply);
                    $log->update(['status' => 'success', 'output_payload' => ['intent' => 'location_pending', 'lat' => $lat, 'lng' => $lng]]);
                    return true;
                }
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'location_no_coords']]);
                return false;
            }

            // ── Handle image DM ───────────────────────────────────────────────
            if ($message->message_type === 'image') {
                $mediaUrl = $message->media_url;
                $rawPayload = $message->raw_payload ?? [];
                $mediaData = $rawPayload['media_data'] ?? null;

                if ($mediaUrl || $mediaData) {
                    $adBuilderState = $this->adBuilder->getState($message->sender_number);
                    $ktpPending = Cache::has('ktp_pending:' . $message->sender_number);

                    if ($ktpPending) {
                        // KTP scan mode — existing flow
                        Cache::forget('ktp_pending:' . $message->sender_number);
                        $this->whacenter->sendMessage($message->sender_number, '⏳ _Sedang membaca gambar, mohon tunggu sebentar..._');
                        $reply = $mediaData && !$mediaUrl
                            ? $this->analyzeKtpBase64($mediaData['data'], $mediaData['mimetype'] ?? 'image/jpeg')
                            : $this->handleKtpImage($mediaUrl);
                        $this->whacenter->sendMessage($message->sender_number, $reply);
                        $log->update(['status' => 'success', 'output_payload' => ['intent' => 'ktp_scan_image']]);
                        return true;
                    }

                    // Ad builder: active session OR auto-start on any image DM
                    if (!$adBuilderState) {
                        // Auto-start — user sent photo without typing "buat iklan" first
                        $contact = Contact::where('phone_number', $message->sender_number)->first();
                        $name = $contact ? $contact->getSapaan($message->sender_name) : ($message->sender_name ?: 'Kak');
                        $this->adBuilder->startSilent($message->sender_number);
                    }
                    $reply = $this->adBuilder->handleImage($message);
                    $this->whacenter->sendMessage($message->sender_number, $reply);
                    $log->update(['status' => 'success', 'output_payload' => ['intent' => 'ad_builder_image']]);
                    return true;
                }
            }

            // ── Ad builder: handle text in active build session ───────────────
            $adBuilderState = $this->adBuilder->getState($message->sender_number);
            if ($adBuilderState) {
                $text = trim($message->raw_body ?? '');
                $step = $adBuilderState['step'] ?? '';
                $reply = match ($step) {
                    'waiting_input' => $this->adBuilder->handleTextWhileWaiting($message->sender_number, $text),
                    'enriching' => $this->adBuilder->handleEnriching($message->sender_number, $text, $adBuilderState),
                    'reviewing' => $this->adBuilder->handleReview($message->sender_number, $text, $adBuilderState),
                    default => $this->adBuilder->handleTextWhileWaiting($message->sender_number, $text),
                };
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $log->update(['status' => 'success', 'output_payload' => ['intent' => 'ad_builder_text', 'step' => $step]]);
                return true;
            }

            // ── Handle sticker / media without text ─────────────────────
            $text = trim($message->raw_body ?? '');
            if (empty($text)) {
                // Stickers, audio notes, video without caption, etc.
                if (in_array($message->message_type, ['sticker', 'audio', 'video'])) {
                    $contact = Contact::where('phone_number', $message->sender_number)->first();
                    $name = $contact ? $contact->getSapaan($message->sender_name) : ($message->sender_name ?: 'Kak');
                    $responses = [
                        '😄👍',
                        "Hehe lucu *{$name}* 😂",
                        '😊🙏',
                        "Wkwk 😆 ada yang bisa aku bantu *{$name}*? Ketik *bantuan* ya 🙏",
                        "🤣👍 Btw, butuh bantuan apa *{$name}*?",
                    ];
                    $reply = $responses[array_rand($responses)];
                    $this->whacenter->sendMessage($message->sender_number, $reply);
                    $log->update(['status' => 'success', 'output_payload' => ['intent' => 'sticker_reply', 'type' => $message->message_type]]);
                    return true;
                }
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'empty_text']]);
                return false;
            }

            // ── Handle reply to location intent question ──────────────────────
            $cacheKey = 'loc_pending:' . $message->sender_number;
            if (Cache::has($cacheKey) && preg_match('/^\s*[12]\s*$/', $text)) {
                $pending = Cache::get($cacheKey);
                Cache::forget($cacheKey);
                $lat = $pending['lat'];
                $lng = $pending['lng'];

                if (trim($text) === '1') {
                    $reply = $this->handleUpdateBusinessLocation($lat, $lng, $message->sender_number);
                } else {
                    $reply = $this->handleLocationSearch($lat, $lng, $message->sender_number);
                }
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $log->update(['status' => 'success', 'output_payload' => ['intent' => trim($text) === '1' ? 'update_location' : 'location_search', 'lat' => $lat, 'lng' => $lng]]);
                return true;
            }

            $categoryNames = Category::where('is_active', true)->pluck('name')->implode(', ');

            // ── Quick terjual shortcut: "terjual #146" or "tandai terjual 146" ───
            if (preg_match('/\b(?:terjual|laku|sold|tandai\s+(?:terjual|laku|sold))\b.*?#?(\d+)|#?(\d+).*?\b(?:terjual|laku|sold)\b/iu', $text, $tjm)) {
                $listingId = (int) ($tjm[1] ?: $tjm[2]);
                if ($listingId > 0) {
                    $reply = $this->markAsSold($message, $listingId);
                    $this->whacenter->sendMessage($message->sender_number, $reply);
                    $log->update(['status' => 'success', 'output_payload' => ['intent' => 'mark_sold', 'listing_id' => $listingId]]);
                    return true;
                }
            }

            // ── Quick aktifkan shortcut: "aktifkan #146" ─────────────────────
            if (preg_match('/\b(?:aktifkan|aktifkan\s+kembali|aktif|reactivate)\b.*?#?(\d+)|#?(\d+).*?\b(?:aktifkan|aktifkan\s+kembali)\b/iu', $text, $akm)) {
                $listingId = (int) ($akm[1] ?: $akm[2]);
                if ($listingId > 0) {
                    $reply = $this->reactivateListing($message, $listingId);
                    $this->whacenter->sendMessage($message->sender_number, $reply);
                    $log->update(['status' => 'success', 'output_payload' => ['intent' => 'reactivate_listing', 'listing_id' => $listingId]]);
                    return true;
                }
            }

            // ── Quick create_ad intent — bypass Gemini for obvious ad-create keywords ──
            if (preg_match('/\b(buat|pasang|bikin|tambah|post|posting)\s+(iklan|jualan|dagangan|produk|listing)\b|\b(iklan\s+baru|pasang\s+iklan|buat\s+iklan|iklan\s+via\s+bot)\b/iu', $text)) {
                $contact = Contact::where('phone_number', $message->sender_number)->first();
                $name = $contact ? $contact->getSapaan($message->sender_name) : ($message->sender_name ?: 'Kak');
                $reply = $this->adBuilder->start($message->sender_number, $name);
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $log->update(['status' => 'success', 'output_payload' => ['intent' => 'create_ad']]);
                return true;
            }

            // ── Quick KTP intent — bypass Gemini for obvious KTP keywords ─────
            if (preg_match('/\b(scan|baca|ocr|ekstrak|upload|foto|kirim|cek|check|identitas)\s+k\.?t\.?p\.?\b|\bk\.?t\.?p\.?\s*(scan|baca|ocr|upload|kirim)\b|^\s*k\.?t\.?p\.?\s*$/i', $text)) {
                $reply = $this->handleKtpScanRequest($message->sender_number);
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $log->update(['status' => 'success', 'output_payload' => ['intent' => 'scan_ktp']]);
                return true;
            }

            // ── Handle pending edit continuation ─────────────────────────────
            $editCacheKey = 'edit_pending:' . $message->sender_number;
            if (Cache::has($editCacheKey)) {
                $pendingEdit = Cache::get($editCacheKey);
                Cache::forget($editCacheKey);
                $isMemberOnly = $pendingEdit['member_only'] ?? false;
                $reply = $isMemberOnly
                    ? $this->applyMemberEdit($message, $pendingEdit['listing_id'], $text)
                    : $this->applyPendingEdit($message, $pendingEdit['listing_id'], $text);
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $log->update(['status' => 'success', 'output_payload' => ['intent' => 'edit_listing_continue', 'listing_id' => $pendingEdit['listing_id']]]);
                return true;
            }

            // ── Bare number: member ketik nomor iklan saja → quick edit ──────
            if (preg_match('/^\s*#?(\d{1,6})\s*$/', $text, $nm)) {
                $listingId = (int) $nm[1];
                $contact = Contact::where('phone_number', $message->sender_number)->first();
                if ($contact && $listingId > 0) {
                    $listing = Listing::where('id', $listingId)
                        ->where(fn($q) => $q->where('contact_id', $contact->id)
                            ->orWhere('contact_number', $message->sender_number))
                        ->first();
                    if ($listing) {
                        $reply = $this->showMemberEditMenu($listing, $message->sender_number);
                        $this->whacenter->sendMessage($message->sender_number, $reply);
                        $log->update(['status' => 'success', 'output_payload' => ['intent' => 'member_quick_edit', 'listing_id' => $listingId]]);
                        return true;
                    }
                }
            }

            // ── Quick edit command — bypass Gemini for obvious edit patterns ──
            if (preg_match('/^(?:edit|ubah|perbarui|update)\s*(?:iklan|listing|#)?\s*#?(\d+)\s*(.*)/iu', $text, $editMatch)) {
                $editId = (int) $editMatch[1];
                $editText = trim($editMatch[2]);
                $reply = $this->handleEditListing($message, $editId, $editText);
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $log->update(['status' => 'success', 'output_payload' => ['intent' => 'edit_listing', 'listing_id' => $editId]]);
                return true;
            }

            // ── Carry clarify context if user is answering a pending question ─
            $clarifyCacheKey = 'clarify_pending:' . $message->sender_number;
            $clarifyContext = null;
            if (Cache::has($clarifyCacheKey)) {
                $clarifyContext = Cache::get($clarifyCacheKey);
                Cache::forget($clarifyCacheKey);
                // Merge original ambiguous message with user's clarification
                $text = "Pesan sebelumnya: \"{$clarifyContext['original']}\" → Klarifikasi user: \"{$text}\"";
            }

            $promptTemplate = Setting::get('prompt_bot_intent', 'Deteksi intent: {text} Kategori: {categoryNames}');
            $prompt = str_replace(['{text}', '{categoryNames}'], [$text, $categoryNames], $promptTemplate);

            $parsed = $this->gemini->generateJson($prompt);

            if (!$parsed) {
                $parsed = $this->fallbackParse($text);
            }

            $intent = $parsed['intent'] ?? 'unknown';
            $categoryQuery = $parsed['category_query'] ?? null;
            $keyword = $parsed['keyword'] ?? null;
            $limit = min((int) ($parsed['limit'] ?? 5), 10);

            $clarifyQuestion = $parsed['clarify_question'] ?? null;

            // ── Override: greeting/conversation containing product price query → search_product
            if (in_array($intent, ['greeting', 'conversation', 'general_question', 'unknown'])) {
                $lowerText = mb_strtolower($text);
                if (preg_match('/\b(harga|berapa|brp|ada\s+jual|jual\s+apa|dijual|ada\s+yang\s+jual|mau\s+beli|cari)\b/i', $lowerText)) {
                    $intent = 'search_product';
                    if (!$keyword) {
                        // Strip greeting prefix and price words to get the product keyword
                        $kw = preg_replace('/^(assalamu\S*\s*,?\s*|wa.?alaikum\S*\s*,?\s*|halo\s*,?\s*|hai\s*,?\s*|bismillah\s*,?\s*)/iu', '', $text);
                        $kw = preg_replace('/\b(harga|berapa|brp|ya|dong|deh|nih|ada|jual|mau\s+beli|cari)\b\s*/iu', ' ', $kw);
                        $kw = trim(preg_replace('/\s+/', ' ', $kw));
                        $keyword = $kw ?: null;
                    }
                }
            }

            $reply = match ($intent) {
                'search_seller' => $this->searchSellers($text, $categoryQuery, $limit),
                'search_product' => $this->searchProducts($text, $categoryQuery, $limit),
                'list_categories' => $this->listCategories(),
                'my_listings' => $this->myListings($message->sender_number, $limit),
                'edit_listing' => $this->handleEditListing($message, (int) ($parsed['listing_id'] ?? 0), $text),
                'create_ad' => $this->handleCreateAd($message),
                'help' => $this->helpMessage(),
                'off_topic' => $this->offTopicReply(),
                'contact_admin' => $this->handleContactAdmin($message->sender_number),
                'scan_ktp' => $this->handleKtpScanRequest($message->sender_number),
                'clarify' => $this->handleClarify($clarifyContext['original'] ?? $text, $message->sender_number, $clarifyQuestion),
                default => $this->handleConversation($text, $message->sender_number),
            };

            $this->whacenter->sendMessage($message->sender_number, $reply);

            $duration = (int) ((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000);
            $log->update([
                'status' => 'success',
                'output_payload' => compact('intent', 'categoryQuery', 'keyword'),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('BotQueryAgent failed', ['error' => $e->getMessage()]);
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return false;
        }
    }

    // ── RAG core ─────────────────────────────────────────────────────────

    /**
     * RAG: Retrieve candidate listings from DB, then ask Gemini to pick
     * the most semantically relevant ones based on the full user query.
     */
    private function ragRetrieveListings(string $userQuery, ?string $category, int $candidateLimit = 100): \Illuminate\Support\Collection
    {
        $q = Listing::with(['contact', 'category'])->where('status', 'active');
        if ($category) {
            $q->whereHas('category', fn($c) => $c->where('name', 'ilike', "%{$category}%"));
        }
        $candidates = $q->latest('source_date')->limit($candidateLimit)->get();

        // Jika filter kategori tidak ada hasilnya, perluas ke semua listing
        if ($candidates->isEmpty() && $category) {
            $candidates = Listing::with(['contact', 'category'])
                ->where('status', 'active')
                ->latest('source_date')
                ->limit($candidateLimit)
                ->get();
        }

        if ($candidates->isEmpty()) {
            return collect();
        }

        // Terlalu sedikit kandidat — langsung kembalikan tanpa panggil Gemini
        if ($candidates->count() <= 3) {
            return $candidates;
        }

        // Buat ringkasan singkat tiap listing untuk dikirim ke Gemini
        $context = $candidates->map(fn($l) =>
            "ID:{$l->id} | {$l->title} | Kategori:{$l->category?->name} | Lokasi:{$l->location} | "
            . Str::limit($l->description ?? '', 80))->implode("\n");

        $promptTemplate = Setting::get('prompt_bot_rag_relevance', 'Query: "{userQuery}" Iklan: {context} Jawab JSON: {"ids":[]}');
        $prompt = str_replace(['{userQuery}', '{context}'], [$userQuery, $context], $promptTemplate);

        $result = $this->gemini->generateJson($prompt);

        // Gemini gagal → fallback kembalikan semua kandidat
        if (is_null($result)) {
            return $candidates;
        }

        $ids = $result['ids'] ?? [];
        if (empty($ids)) {
            return collect();
        }

        $byId = $candidates->keyBy('id');
        return collect($ids)
            ->map(fn($id) => $byId->get((int) $id))
            ->filter()
            ->values();
    }

    // ── Query handlers ──────────────────────────────────────────────────

    private function searchProducts(string $userQuery, ?string $category, int $limit): string
    {
        $listings = $this->ragRetrieveListings($userQuery, $category)->take($limit);

        if ($listings->isEmpty()) {
            $what = $category ?? $userQuery;
            return "😔 Maaf, belum ada iklan *{$what}* yang tersedia saat ini.\n\n_Coba cari dengan kata lain._";
        }

        $lines = ["🛍️ *{$listings->count()} Produk Ditemukan*\n"];
        foreach ($listings as $i => $listing) {
            $seller = $listing->contact?->name ?? $listing->contact_name ?? 'Penjual';
            $price = $listing->price_formatted;
            $loc = $listing->location ? " 📍_{$listing->location}_" : '';
            $link = "{$this->baseUrl}/p/{$listing->id}";
            $lines[] = ($i + 1) . ". *{$listing->title}*\n"
                . "   💰 {$price}{$loc}\n"
                . "   👤 {$seller}\n"
                . "   🔗 {$link}";
        }
        $lines[] = "\n_Kunjungi link untuk detail & info kontak penjual._";
        return implode("\n", $lines);
    }

    private function searchSellers(string $userQuery, ?string $category, int $limit): string
    {
        $listings = $this->ragRetrieveListings($userQuery, $category, 100);

        if ($listings->isEmpty()) {
            $what = $category ?? $userQuery;
            return "😔 Maaf, belum ada penjual *{$what}* yang terdaftar saat ini.\n\n_Coba cari dengan kata lain._";
        }

        // Kelompokkan listing relevan per penjual
        $sellers = $listings
            ->filter(fn($l) => $l->contact !== null)
            ->groupBy('contact_id')
            ->map(fn($group) => [
                'contact' => $group->first()->contact,
                'listing_count' => $group->count(),
                'products' => $group->pluck('title')->take(3)->implode(', '),
            ])
            ->sortByDesc('listing_count')
            ->take($limit)
            ->values();

        if ($sellers->isEmpty()) {
            return '😔 Maaf, belum ada penjual yang relevan ditemukan.';
        }

        $lines = ["🏪 *Penjual yang Relevan*\n"];
        foreach ($sellers as $i => $s) {
            $contact = $s['contact'];
            $name = $contact->name ?? $contact->phone_number;
            $link = "{$this->baseUrl}/u/{$contact->phone_number}";
            $lines[] = ($i + 1) . ". *{$name}*\n"
                . "   📦 {$s['listing_count']} iklan\n"
                . "   🏷️ _{$s['products']}_\n"
                . "   🔗 {$link}";
        }
        $lines[] = "\n_Klik link untuk lihat semua iklan penjual._";
        return implode("\n", $lines);
    }

    private function listCategories(): string
    {
        $categories = Category::where('is_active', true)
            ->withCount(['listings' => fn($q) => $q->where('status', 'active')])
            ->orderByDesc('listings_count')
            ->get();

        if ($categories->isEmpty()) {
            return 'Belum ada kategori produk terdaftar.';
        }

        $lines = ["📂 *Kategori Produk Marketplace Jamaah*\n"];
        foreach ($categories as $cat) {
            $count = $cat->listings_count;
            $lines[] = "• *{$cat->name}* ({$count} iklan)";
        }
        $lines[] = "\n_Ketik nama kategori untuk mencari penjual atau produk._\n_Contoh: \"cari penjual makanan\" atau \"tampilkan produk hijab\"_";

        return implode("\n", $lines);
    }

    private function myListings(string $phoneNumber, int $limit): string
    {
        $contact = Contact::where('phone_number', $phoneNumber)->first();
        if (!$contact) {
            return '❌ Nomor kamu belum terdaftar. Kirim pesan di grup Marketplace Jamaah terlebih dahulu.';
        }

        $listings = Listing::where('contact_id', $contact->id)
            ->orWhere('contact_number', $phoneNumber)
            ->latest('source_date')
            ->limit($limit)
            ->get();

        if ($listings->isEmpty()) {
            return "📭 Kamu belum memiliki iklan aktif di Marketplace Jamaah.\n\n_Posting iklan di grup untuk mulai berjualan!_";
        }

        $lines = ["📋 *Iklan Kamu ({$listings->count()} terakhir)*\n"];
        foreach ($listings as $i => $listing) {
            $status = match ($listing->status) {
                'active' => '🟢',
                'sold' => '✅',
                default => '🟡',
            };
            $link = "{$this->baseUrl}/p/{$listing->id}";
            $lines[] = ($i + 1) . ". {$status} *#{$listing->id}* — *{$listing->title}*\n"
                . "   💰 {$listing->price_formatted}\n"
                . "   🔗 {$link}";
        }
        $lines[] = "\n✏️ _Mau edit? Ketik *edit #<nomor>*, misal: *edit #{$listings->first()->id}*_";

        return implode("\n", $lines);
    }

    // ── Location search ──────────────────────────────────────────────────────

    /**
     * Handle a WhatsApp shared-location message from a DM.
     * 1. Ask Gemini to identify the area from coordinates.
     * 2. RAG-search listings that match the area.
     */
    private function handleLocationSearch(float $lat, float $lng, string $senderPhone): string
    {
        // Step 1: Reverse-geocode via Gemini
        $geoTemplate = Setting::get('prompt_bot_reverse_geocode', 'Koordinat GPS: latitude {lat}, longitude {lng}. Area apa di Indonesia?');
        $areaPrompt = str_replace(['{lat}', '{lng}'], [$lat, $lng], $geoTemplate);

        $areaName = trim($this->gemini->generateContent($areaPrompt) ?? '');

        // Step 2: RAG search with area context
        $userQuery = 'produk atau penjual di sekitar ' . ($areaName ?: "koordinat {$lat},{$lng}");
        $listings = $this->ragRetrieveListings($userQuery, null, 100);

        $areaLabel = $areaName ? "*{$areaName}*" : 'lokasi yang kamu bagikan';

        if ($listings->isEmpty()) {
            return "📍 Terima kasih sudah berbagi lokasi di {$areaLabel}.\n\n"
                . "😔 Belum ada iklan yang cocok dengan lokasimu saat ini.\n\n"
                . '_Coba ketik nama produk yang kamu cari, misalnya: "ada gamis ukuran L?"_';
        }

        $lines = ["📍 *Produk Sekitar {$areaLabel}*\n"];
        foreach ($listings->take(5) as $i => $listing) {
            $link = "{$this->baseUrl}/p/{$listing->id}";
            $price = $listing->price_label
                ?? ($listing->price > 0 ? 'Rp ' . number_format($listing->price, 0, ',', '.') : 'Harga nego');
            $loc = $listing->location ? " 📍 {$listing->location}" : '';
            $seller = $listing->contact?->name ?? 'Penjual';
            $lines[] = ($i + 1) . ". *{$listing->title}*\n"
                . "   💰 {$price}{$loc}\n"
                . "   👤 {$seller}\n"
                . "   🔗 {$link}";
        }
        $lines[] = "\n_Ketik nama produk untuk pencarian lebih spesifik._";

        return implode("\n", $lines);
    }

    // ── Clarify ──────────────────────────────────────────────────────────────

    /**
     * When Gemini deems the message too ambiguous, ask a clarifying question
     * and store the original text in cache so the next reply uses the context.
     */
    private function handleClarify(string $originalText, string $phone, ?string $question): string
    {
        $cacheKey = 'clarify_pending:' . $phone;
        Cache::put($cacheKey, ['original' => $originalText], now()->addMinutes(5));

        $defaultQuestion = "🤔 Bisa diperjelas maksudnya?\n\n"
            . "Misalnya:\n"
            . "• *Cari produk* → \"cari gamis ukuran L\"\n"
            . "• *Cari penjual* → \"siapa yang jual makanan?\"\n"
            . "• *Info iklan saya* → \"iklanku ada apa?\"\n\n"
            . '_Atau ketik *bantuan* untuk lihat semua fitur._';

        return $question
            ? "🤔 {$question}\n\n_Atau ketik *bantuan* untuk lihat semua fitur._"
            : $defaultQuestion;
    }

    // ── Update business location ─────────────────────────────────────────────

    private function handleUpdateBusinessLocation(float $lat, float $lng, string $senderPhone): string
    {
        // Reverse-geocode via Gemini
        $geoTemplate = Setting::get('prompt_bot_reverse_geocode', 'Koordinat GPS: latitude {lat}, longitude {lng}. Area apa di Indonesia?');
        $areaPrompt = str_replace(['{lat}', '{lng}'], [$lat, $lng], $geoTemplate);

        $areaName = trim($this->gemini->generateContent($areaPrompt) ?? '');
        $address = $areaName ?: "lat:{$lat}, lng:{$lng}";

        $updated = Contact::where('phone_number', $senderPhone)
            ->update(['latitude' => $lat, 'longitude' => $lng, 'address' => $address]);

        if ($updated) {
            return "✅ *Lokasi bisnis berhasil diperbarui!*\n\n"
                . "📍 Lokasi kamu sekarang tercatat di *{$address}*\n\n"
                . '_Lokasi ini akan ditampilkan di profil penjualmu._';
        }

        return '❌ Gagal memperbarui lokasi. Pastikan kamu sudah terdaftar sebagai member.';
    }

    private function helpMessage(): string
    {
        return "🤖 *Kemampuan Bot Marketplace Jamaah*\n\n"
            . "🛍️ *Buat Iklan via DM (Baru!)*\n"
            . "_\"buat iklan\"_ atau _\"pasang iklan\"_\n"
            . "→ Kirim foto + AI polishing otomatis → setujui → tayang di grup!\n\n"
            . "📦 *Iklan Otomatis via Grup*\n"
            . "Posting jualan di grup seperti biasa — AI deteksi, ekstrak, dan tayangkan otomatis.\n\n"
            . "🔍 *Cari Produk/Penjual (DM ke bot)*\n"
            . "_\"Siapa yang jual gamis ukuran L?\"_\n"
            . "_\"Ada kurma Ajwa tidak?\"_\n"
            . "_\"Penjual kue basah di Bekasi\"_\n"
            . "→ Pakai AI semantic search, bukan sekedar keyword\n\n"
            . "📍 *Kirim Lokasi via WhatsApp*\n"
            . "Kirim pin lokasi → bot tanya tujuan:\n"
            . "1️⃣ Update lokasi bisnis di profil\n"
            . "2️⃣ Cari produk di sekitar area\n\n"
            . "📋 *Kelola Iklan Saya*\n"
            . "_\"iklanku\"_ → lihat semua iklanmu\n\n"
            . "✏️ *Edit Iklan (Ketik Nomor Iklan Saja!)*\n"
            . "_\"146\"_ → bot tampilkan iklan #146 + menu edit\n"
            . "Lalu balas:\n"
            . "• _harga 500000_ → ubah harga\n"
            . "• _terjual_ → tandai sudah laku\n"
            . "• _sembunyikan_ → hapus dari etalase\n"
            . "• _aktifkan_ → tampilkan kembali\n\n"
            . "📂 *Lihat Kategori*\n"
            . "_\"Daftar kategori tersedia\"_\n\n"
            . "🤔 *AI Tanya Balik* jika pesan kurang jelas\n\n"
            . "⚠️ _Bot ini khusus marketplace. Pertanyaan di luar topik jual beli tidak dilayani._\n\n"
            . "🪪 *Scan KTP*\n"
            . "Ketik *scan ktp* → kirim foto KTP → AI baca otomatis\n\n"
            . "🌐 {$this->baseUrl}";
    }

    private function handleKtpScanRequest(string $phoneNumber): string
    {
        $cacheKey = 'ktp_pending:' . $phoneNumber;
        Cache::put($cacheKey, true, now()->addMinutes(10));

        return "📋 *Scan KTP dengan AI*\n\n"
            . "Silakan kirim foto KTP kamu sekarang.\n\n"
            . "🤖 AI akan membaca dan mengekstrak:\n"
            . "• NIK\n"
            . "• Nama lengkap\n"
            . "• Tempat/tanggal lahir\n"
            . "• Alamat lengkap\n"
            . "• Agama, status perkawinan, pekerjaan\n\n"
            . "_Pastikan foto KTP jelas, tidak buram, dan seluruh kartu terlihat._\n"
            . '_Data hanya dibaca, tidak disimpan._';
    }

    private function handleKtpImage(string $mediaUrl): string
    {
        try {
            $imageData = Http::timeout(15)->get($mediaUrl);
            if ($imageData->failed()) {
                return '❌ Gagal mengunduh gambar. Silakan coba kirim ulang foto KTP kamu.';
            }

            $base64 = base64_encode($imageData->body());
            $mimeType = $imageData->header('Content-Type') ?: 'image/jpeg';

            return $this->analyzeKtpBase64($base64, $mimeType);
        } catch (\Exception $e) {
            Log::error('BotQueryAgent::handleKtpImage failed', ['error' => $e->getMessage()]);
            return '❌ Terjadi kesalahan saat membaca KTP. Silakan coba lagi.';
        }
    }

    private function analyzeKtpBase64(string $base64, string $mimeType): string
    {
        try {
            $prompt = Setting::get('prompt_bot_ktp_scan', 'Baca KTP Indonesia dari gambar. Jawab JSON.');

            $result = $this->gemini->analyzeImageWithText($base64, $mimeType, $prompt);
            if (!$result) {
                return '❌ Gagal membaca gambar. Silakan coba lagi dengan foto yang lebih jelas.';
            }

            $clean = preg_replace('/```json\s*/i', '', $result);
            $clean = preg_replace('/```\s*/i', '', $clean);
            $parsed = json_decode(trim($clean), true);

            if (!$parsed || !($parsed['is_ktp'] ?? false)) {
                return "📷 *Foto Bukan KTP*\n\n"
                    . "Gambar yang dikirim tidak terdeteksi sebagai KTP.\n\n"
                    . "Untuk scan KTP, ketik *scan ktp* lalu kirim foto KTP kamu.\n\n"
                    . "_Tips foto KTP yang baik:\n"
                    . "• Pencahayaan cukup, tidak gelap\n"
                    . "• Seluruh KTP terlihat, tidak terpotong\n"
                    . '• Foto tidak buram/blur_';
            }

            $lines = ["🪪 *Hasil Scan KTP*\n"];
            if (!empty($parsed['nik']))
                $lines[] = "📌 *NIK:* {$parsed['nik']}";
            if (!empty($parsed['nama']))
                $lines[] = "👤 *Nama:* {$parsed['nama']}";
            $ttl = trim(($parsed['tempat_lahir'] ?? '') . ', ' . ($parsed['tanggal_lahir'] ?? ''), ', ');
            if ($ttl !== ',' && $ttl !== '')
                $lines[] = "🎂 *Tgl Lahir:* {$ttl}";
            if (!empty($parsed['jenis_kelamin']))
                $lines[] = "⚧ *Jenis Kelamin:* {$parsed['jenis_kelamin']}";
            $alamatBagian = array_filter([
                $parsed['alamat'] ?? null,
                !empty($parsed['rt_rw']) ? 'RT/RW ' . $parsed['rt_rw'] : null,
                $parsed['kelurahan'] ?? null,
                !empty($parsed['kecamatan']) ? 'Kec. ' . $parsed['kecamatan'] : null,
                $parsed['kabupaten_kota'] ?? null,
                $parsed['provinsi'] ?? null,
            ]);
            if (!empty($alamatBagian))
                $lines[] = '🏠 *Alamat:* ' . implode(', ', $alamatBagian);
            if (!empty($parsed['agama']))
                $lines[] = "🕌 *Agama:* {$parsed['agama']}";
            if (!empty($parsed['status_perkawinan']))
                $lines[] = "💍 *Status:* {$parsed['status_perkawinan']}";
            if (!empty($parsed['pekerjaan']))
                $lines[] = "💼 *Pekerjaan:* {$parsed['pekerjaan']}";
            if (!empty($parsed['berlaku_hingga']))
                $lines[] = "📅 *Berlaku Hingga:* {$parsed['berlaku_hingga']}";
            $lines[] = "\n_⚠️ Data di atas hanya hasil pembacaan AI dan tidak disimpan._";
            $lines[] = '_Pastikan keakuratan dengan membandingkan langsung ke KTP fisik._';

            return implode("\n", $lines);
        } catch (\Exception $e) {
            Log::error('BotQueryAgent::analyzeKtpBase64 failed', ['error' => $e->getMessage()]);
            return '❌ Terjadi kesalahan saat membaca KTP. Silakan coba lagi.';
        }
    }

    /**
     * Mark a listing as sold quickly (shortcut: "terjual #146").
     * Only owner or master can mark as sold.
     */
    private function markAsSold(Message $message, int $listingId): string
    {
        $phone = $message->sender_number;
        $isMaster = MasterCommandAgent::isMasterPhone($phone);
        $contact = Contact::where('phone_number', $phone)->first();

        $listing = Listing::find($listingId);
        if (!$listing) {
            return "❌ Iklan #{$listingId} tidak ditemukan.";
        }

        if (!$isMaster) {
            $isOwner = ($contact && $listing->contact_id === $contact->id)
                || $listing->contact_number === $phone;
            if (!$isOwner) {
                return "🚫 Kamu tidak bisa mengubah iklan #{$listingId} karena bukan milikmu.";
            }
        }

        if ($listing->status === 'sold') {
            return "ℹ️ Iklan *#{$listing->id} — {$listing->title}* sudah ditandai terjual sebelumnya.";
        }

        $listing->update(['status' => 'sold']);

        // Announce to WAG that item is sold
        $group = \App\Models\WhatsappGroup::where('is_active', true)->first();
        if ($group) {
            $sellerName = $listing->contact_name ?? $contact?->name ?? 'Penjual';
            $wagMsg = "✅ *TERJUAL!*\n\n"
                . "📦 *{$listing->title}* (#️⃣{$listing->id})\n"
                . "👤 Penjual: {$sellerName}\n\n"
                . "_Iklan ini sudah tidak tersedia._";
            try {
                $this->whacenter->sendGroupMessage($group->group_name, $wagMsg);
            } catch (\Exception $e) {
                Log::warning('markAsSold: gagal kirim notif WAG', ['error' => $e->getMessage()]);
            }
        }

        return "✅ *Iklan #{$listing->id} — {$listing->title}* berhasil ditandai *TERJUAL!*\n\n"
            . "📭 Iklan sudah dihapus dari etalase marketplace.\n\n"
            . "_Ketik *aktifkan #$listing->id* jika ingin mengaktifkan kembali._";
    }

    /**
     * Show a simple edit menu for member's own listing.
     * Triggered when member types just the listing number.
     */
    private function showMemberEditMenu(Listing $listing, string $phone): string
    {
        $status = match ($listing->status) {
            'active'   => '🟢 Aktif (tayang)',
            'sold'     => '✅ Terjual',
            'inactive' => '🔴 Disembunyikan',
            'expired'  => '🟡 Kadaluarsa',
            default    => $listing->status,
        };

        // Store pending edit with member_only flag
        Cache::put('edit_pending:' . $phone, [
            'listing_id'  => $listing->id,
            'member_only' => true,
        ], now()->addMinutes(10));

        $priceTypeLabel = match ($listing->price_type ?? 'fix') {
            'nego'   => '🤝 Nego',
            'lelang' => '🔨 Lelang',
            default  => '🏷️ Fix',
        };

        return "📦 *Iklan #{$listing->id}*\n"
            . "*{$listing->title}*\n\n"
            . "💰 Harga: {$listing->price_formatted}\n"
            . "🏷️ Tipe harga: {$priceTypeLabel}\n"
            . "Status: {$status}\n\n"
            . "─────────────────\n"
            . "Balas dengan perintah:\n\n"
            . "💰 *Ubah Harga:*\n"
            . "• *harga fix 500000* → Rp 500.000 (Harga Tetap)\n"
            . "• *harga nego 500000* → Rp 500.000 (Nego)\n"
            . "• *harga nego* → Harga Nego (tanpa angka)\n"
            . "• *harga lelang 1000000* → Lelang mulai Rp 1.000.000\n\n"
            . "📋 *Status Iklan:*\n"
            . "• *terjual* → tandai sudah laku\n"
            . "• *sembunyikan* → hapus dari etalase\n"
            . "• *aktifkan* → tampilkan kembali\n\n"
            . "_Atau ketik *batal* untuk keluar._";
    }

    /**
     * Apply a member-limited edit (price + status only).
     * Called when edit_pending has member_only=true.
     */
    private function applyMemberEdit(Message $message, int $listingId, string $text): string
    {
        if (preg_match('/^\s*(batal|cancel)\s*$/iu', $text)) {
            return '👌 Oke, tidak ada yang diubah.';
        }

        $phone = $message->sender_number;
        $contact = Contact::where('phone_number', $phone)->first();
        $isMaster = MasterCommandAgent::isMasterPhone($phone);

        $listing = Listing::find($listingId);
        if (!$listing) {
            return "❌ Iklan #{$listingId} tidak ditemukan.";
        }

        // Ownership check
        if (!$isMaster) {
            $isOwner = ($contact && $listing->contact_id === $contact->id)
                || $listing->contact_number === $phone;
            if (!$isOwner) {
                return "🚫 Kamu tidak bisa mengubah iklan ini karena bukan milikmu.";
            }
        }

        $lower = mb_strtolower(trim($text));
        $changes = [];
        $desc = [];

        // Status commands
        if (preg_match('/^\s*(terjual|laku|sold)\s*$/iu', $lower)) {
            if ($listing->status === 'sold') {
                return "ℹ️ Iklan ini sudah ditandai terjual sebelumnya.";
            }
            $changes['status'] = 'sold';
            $desc[] = "Status → *Terjual* ✅";
        } elseif (preg_match('/^\s*(sembunyikan|sembunyikan\s+iklan|hidden|nonaktif|hide)\s*$/iu', $lower)) {
            $changes['status'] = 'inactive';
            $desc[] = "Status → *Disembunyikan* 🔴 (tidak tayang di etalase)";
        } elseif (preg_match('/^\s*(aktifkan|tampilkan\s+lagi|aktif|aktifkan\s+kembali)\s*$/iu', $lower)) {
            $changes['status'] = 'active';
            $desc[] = "Status → *Aktif* 🟢 (tayang di etalase)";
        }
        // Price commands: "harga fix 500000" / "harga nego 750000" / "harga lelang 1jt" / "harga nego"
        elseif (preg_match('/^\s*harga\s+(.+)$/iu', $text, $hm) || preg_match('/^\s*(\d[\d.,]*[kKmM]?)\s*$/u', $text, $hm)) {
            $rawPrice = trim($hm[1]);

            // Detect price_type from keyword
            $priceType = 'fix';
            if (preg_match('/\b(nego|negotiable|negosiasi)\b/iu', $rawPrice)) {
                $priceType = 'nego';
                $rawPrice = trim(preg_replace('/\b(nego|negotiable|negosiasi)\b\s*/iu', '', $rawPrice));
            } elseif (preg_match('/\b(lelang|auction|bid)\b/iu', $rawPrice)) {
                $priceType = 'lelang';
                $rawPrice = trim(preg_replace('/\b(lelang|auction|bid)\b\s*/iu', '', $rawPrice));
            } elseif (preg_match('/\b(fix|fixed|tetap|pasti)\b/iu', $rawPrice)) {
                $priceType = 'fix';
                $rawPrice = trim(preg_replace('/\b(fix|fixed|tetap|pasti)\b\s*/iu', '', $rawPrice));
            }

            $changes['price_type'] = $priceType;
            $changes['price_label'] = null;

            // Parse numeric value (support: 500000 / 500.000 / 500k / 1.5jt / 1jt)
            $numericValue = null;
            if (preg_match('/(\d[\d.,]*)\s*(jt|juta)/iu', $rawPrice, $jm)) {
                $numericValue = (int) round((float) str_replace(['.', ','], ['', '.'], $jm[1]) * 1000000);
            } elseif (preg_match('/(\d[\d.,]*)\s*[kK]\b/', $rawPrice, $km)) {
                $numericValue = (int) round((float) str_replace(['.', ','], ['', '.'], $km[1]) * 1000);
            } elseif (preg_match('/^[\d.,]+$/', $rawPrice)) {
                $numericValue = (int) preg_replace('/[^\d]/', '', $rawPrice);
            }

            if ($numericValue && $numericValue > 0) {
                $changes['price'] = $numericValue;
                $rpStr = 'Rp ' . number_format($numericValue, 0, ',', '.');
                $typeLabel = match ($priceType) {
                    'nego'   => "(Nego)",
                    'lelang' => "— Harga Lelang",
                    default  => "(Fix)",
                };
                $desc[] = "Harga → *{$rpStr} {$typeLabel}*";
            } elseif ($priceType !== 'fix') {
                // No number but valid type (e.g. "harga nego")
                $changes['price'] = null;
                $typeLabel = match ($priceType) {
                    'nego'   => 'Nego',
                    'lelang' => 'Lelang',
                    default  => 'Fix',
                };
                $desc[] = "Harga → *{$typeLabel}*";
            } else {
                return "❓ Tidak dimengerti. Contoh:\n• *harga fix 500000*\n• *harga nego 750000*\n• *harga lelang 1000000*\n• *harga nego* (tanpa angka)\n• *terjual*\n• *sembunyikan*";
            }
        }

        if (empty($changes)) {
            return "❓ Tidak dimengerti. Coba:\n• *harga 500000*\n• *harga nego*\n• *terjual*\n• *sembunyikan*\n• *batal*";
        }

        $listing->update($changes);
        $listing->refresh();

        $group = \App\Models\WhatsappGroup::where('is_active', true)->first();
        $link = "{$this->baseUrl}/p/{$listing->id}";

        // Terjual → announce WAG, no re-post
        if (($changes['status'] ?? '') === 'sold') {
            if ($group) {
                $sellerName = $listing->contact_name ?? $contact?->name ?? 'Penjual';
                try {
                    $this->whacenter->sendGroupMessage($group->group_name,
                        "✅ *TERJUAL!*\n\n📦 *{$listing->title}* (#️⃣{$listing->id})\n"
                        . "👤 Penjual: {$sellerName}\n\n_Iklan ini sudah tidak tersedia._"
                    );
                } catch (\Exception $e) {
                    Log::warning('applyMemberEdit: gagal kirim notif terjual ke WAG', ['error' => $e->getMessage()]);
                }
            }
            return "✅ *Iklan #{$listing->id}* berhasil ditandai *TERJUAL!*\n\n"
                . "📭 Iklan sudah dihapus dari etalase marketplace.";
        }

        // Sembunyikan → no WAG post
        if (($changes['status'] ?? '') === 'inactive') {
            return "✅ *Iklan #{$listing->id}* berhasil diperbarui!\n\n"
                . implode("\n", $desc)
                . "\n\n📭 Iklan tidak lagi tayang di etalase.\n_Ketik *{$listing->id}* → *aktifkan* jika ingin tampilkan lagi._";
        }

        // Harga/tipe berubah → re-post ke WAG dengan format clean
        if ($group && $listing->status === 'active') {
            $priceStr   = $listing->price_formatted;
            $catLine    = $listing->category ? "📂 {$listing->category->name}\n" : '';
            $locLine    = $listing->location ? "📍 {$listing->location}\n" : '';
            $mediaUrls  = $listing->media_urls ?? [];
            $firstImage = !empty($mediaUrls) ? $mediaUrls[0] : null;
            $shortDesc  = $listing->description ? Str::limit(explode("\n", $listing->description)[0], 120) : '';
            $descLine   = $shortDesc ? "_{$shortDesc}_\n" : '';

            $wagCaption = "✏️ *[UPDATE] {$listing->title}*\n"
                . $descLine
                . "💰 {$priceStr}\n"
                . $catLine
                . $locLine
                . "\n🔗 {$link}";

            try {
                if ($firstImage) {
                    $this->whacenter->sendGroupImageMessage($group->group_name, $wagCaption, $firstImage);
                } else {
                    $this->whacenter->sendGroupMessage($group->group_name, $wagCaption);
                }
            } catch (\Exception $e) {
                Log::warning('applyMemberEdit: gagal re-post ke WAG', ['error' => $e->getMessage()]);
            }
        }

        return "✅ *Iklan #{$listing->id}* berhasil diperbarui!\n\n"
            . implode("\n", $desc)
            . "\n\n🔗 {$link}";
    }

    private function reactivateListing(Message $message, int $listingId): string
    {
        $phone = $message->sender_number;
        $isMaster = MasterCommandAgent::isMasterPhone($phone);
        $contact = Contact::where('phone_number', $phone)->first();

        $listing = Listing::find($listingId);
        if (!$listing) {
            return "❌ Iklan #{$listingId} tidak ditemukan.";
        }

        if (!$isMaster) {
            $isOwner = ($contact && $listing->contact_id === $contact->id)
                || $listing->contact_number === $phone;
            if (!$isOwner) {
                return "🚫 Kamu tidak bisa mengaktifkan iklan #{$listingId} karena bukan milikmu.";
            }
        }

        $listing->update(['status' => 'active']);
        $link = "{$this->baseUrl}/p/{$listing->id}";

        return "✅ *Iklan #{$listing->id} — {$listing->title}* berhasil *diaktifkan kembali!*\n\n"
            . "🟢 Iklan sudah tayang di etalase marketplace.\n"
            . "🔗 {$link}";
    }

    private function handleCreateAd(Message $message): string
    {
        $contact = Contact::where('phone_number', $message->sender_number)->first();
        $name = $contact ? $contact->getSapaan($message->sender_name) : ($message->sender_name ?: 'Kak');
        $isMaster = MasterCommandAgent::isMasterPhone($message->sender_number);
        $onBehalfPasmal = $isMaster && AdBuilderAgent::isOnBehalfPasmal($message->raw_body ?? $message->content ?? '');
        return $this->adBuilder->start($message->sender_number, $name, $onBehalfPasmal);
    }

    private function offTopicReply(): string
    {
        return "Untuk itu aku belum bisa bantu ya — fokusnya soal jual beli di Marketplace Jamaah 🛒\n\n"
            . 'Ketik *bantuan* untuk lihat fitur lengkapnya 👇';
    }

    private function handleConversation(string $text, string $phoneNumber): string
    {
        $contact = Contact::where('phone_number', $phoneNumber)->first();
        // Gunakan getSapaan() agar honorific sudah tersimpan (default "Kak" jika belum diketahui),
        // sehingga Gemini tidak perlu menebak sapaan gendered (Mbak/Mas/dll) sendiri.
        $name = $contact ? $contact->getSapaan() : 'Kak';

        // Cek apakah ini pesan pertama (belum pernah ada pesan sebelumnya)
        $previousMessageCount = Message::where('sender_number', $phoneNumber)->count();
        $isFirstMessage = $previousMessageCount <= 1;

        $totalListings = Listing::where('status', 'active')->count();
        $totalSellers = Contact::where('is_registered', true)
            ->whereIn('member_role', ['seller', 'both'])
            ->count();
        $categoryNames = Category::where('is_active', true)->pluck('name')->implode(', ');

        $memberContext = $contact?->is_registered
            ? 'Member terdaftar (penjual/pembeli aktif di marketplace)'
            : ($contact ? 'Anggota grup marketplace (belum daftar formal via bot)' : 'Pengguna baru');

        $greetingRule = $isFirstMessage
            ? 'Ini adalah pesan PERTAMA dari member, boleh membuka dengan salam singkat (maks 1 baris).'
            : 'Ini BUKAN pesan pertama. JANGAN tambahkan salam, sapaan, atau basa-basi pembuka. Langsung jawab.';

        $promptTemplate = Setting::get('prompt_bot_conversation', 'Admin Marketplace Jamaah. Pesan: "{text}". Balas natural.');
        $prompt = str_replace(
            ['{text}', '{memberContext}', '{name}', '{totalListings}', '{totalSellers}', '{categoryNames}', '{baseUrl}', '{greetingRule}'],
            [$text, $memberContext, $name, $totalListings, $totalSellers, $categoryNames, $this->baseUrl, $greetingRule],
            $promptTemplate
        );

        $reply = $this->gemini->generateContent($prompt);

        // Jika Gemini mendeteksi off-topic, return pesan standar tanpa menjawab pertanyaan
        if (!$reply || strtoupper(trim($reply)) === 'OFFTOPIC' || stripos(trim($reply), 'OFFTOPIC') === 0) {
            return $this->offTopicReply();
        }

        // Extract and apply [UPDATE:...] tag if present
        if (preg_match('/\[UPDATE:(.+?)\]/', $reply, $updateMatch)) {
            $reply = trim(preg_replace('/\[UPDATE:.+?\]/', '', $reply));
            $this->applyContactUpdate($phoneNumber, $updateMatch[1]);
        }

        if (!$reply) {
            return "Maaf, saya tidak mengerti pesan kamu. Bisa dijelaskan lebih detail? 🙏\n\nKetik *bantuan* untuk lihat fitur tersedia.";
        }

        return $reply;
    }

    // ── Fallback parser ─────────────────────────────────────────────────

    private function fallbackParse(string $text): array
    {
        $lower = mb_strtolower($text);

        if (preg_match('/\b(assalamu|wa.?alaikum|halo|hai|\bhi\b|selamat\s+(pagi|siang|sore|malam)|apa kabar|permisi)\b/i', $lower)) {
            return ['intent' => 'greeting', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        }
        if (preg_match('/\b(kategori|daftar|list)\b/', $lower)) {
            return ['intent' => 'list_categories', 'category_query' => null, 'keyword' => null, 'limit' => 20];
        }
        if (preg_match('/\b(iklan(ku)?|jualanku|produkku|daganganku)\b/', $lower)) {
            return ['intent' => 'my_listings', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        }
        if (preg_match('/\b(edit|ubah|perbarui|update)\s*(iklan|listing|#)?\s*#?(\d+)/i', $lower, $em)) {
            return ['intent' => 'edit_listing', 'listing_id' => (int) $em[3], 'category_query' => null, 'keyword' => null, 'limit' => 5];
        }
        if (preg_match('/\b(buat|pasang|bikin|tambah|post|posting)\s+(iklan|jualan|dagangan|produk|listing)\b|\b(iklan\s+baru|pasang\s+iklan|buat\s+iklan)\b/iu', $lower)) {
            return ['intent' => 'create_ad', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        }
        if (preg_match('/\b(bantuan|help|info|apa yang|bisa apa)\b/', $lower)) {
            return ['intent' => 'help', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        }
        if (preg_match('/k\.?t\.?p\.?/i', $lower)) {
            return ['intent' => 'scan_ktp', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        }
        if (preg_match('/\b(admin|pembuat|developer|yang buat|mau ketemu|bicara|hubungi|kontak)\b.*\b(admin|bot|sistem|manusia|asli)\b|\b(admin|bot|sistem)\b.*\b(siapa|buat|bikin|ketemu|bicara|hubungi)\b/i', $lower)) {
            return ['intent' => 'contact_admin', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        }
        if (preg_match('/\b(penjual|seller|toko|jualan|jual)\b/', $lower)) {
            $kw = preg_replace('/^.*(penjual|seller|toko|jual)\s*/i', '', $text);
            return ['intent' => 'search_seller', 'category_query' => trim($kw) ?: null, 'keyword' => null, 'limit' => 5];
        }
        if (preg_match('/\b(harga|berapa|brp)\b/i', $lower)) {
            // Asking about price of a product → search for it
            $kw = preg_replace('/^(assalamu\S*\s*,?\s*|wa.?alaikum\S*\s*,?\s*|halo\s*,?\s*|hai\s*,?\s*)/iu', '', $text);
            $kw = preg_replace('/\b(harga|berapa|brp|ya|dong|deh|nih|ada|jual)\b\s*/iu', ' ', $kw);
            $kw = trim(preg_replace('/\s+/', ' ', $kw));
            return ['intent' => 'search_product', 'category_query' => null, 'keyword' => $kw ?: null, 'limit' => 5];
        }
        if (preg_match('/\b(cari|tampilkan|ada|produk|barang|beli)\b/', $lower)) {
            $kw = preg_replace('/^.*(cari|tampilkan|ada|produk|barang|beli)\s*/i', '', $text);
            return ['intent' => 'search_product', 'category_query' => null, 'keyword' => trim($kw) ?: null, 'limit' => 5];
        }

        // Default: treat as conversational — let Gemini handle it
        return ['intent' => 'conversation', 'category_query' => null, 'keyword' => null, 'limit' => 5];
    }

    /**
     * Handle request to contact admin or ask about bot creator.
     * Forward the message to the real admin's WA.
     */
    private function handleContactAdmin(string $senderPhone): string
    {
        $adminPhone = config('services.wa_gateway.admin_phone', '6285719195627');
        $contact = Contact::where('phone_number', $senderPhone)->first();
        $name = $contact?->name ?? $senderPhone;

        // Forward notification to admin
        $this->whacenter->sendMessage(
            $adminPhone,
            "📩 *Pesan dari member:*\n\n"
                . "Nama: *{$name}*\n"
                . "No: {$senderPhone}\n\n"
                . 'Member ini mau bicara/ketemu admin. Silakan hubungi langsung ya 🙏'
        );

        return "Oke *{$name}*, udah aku sampaikan ke admin ya! 😊\n\n"
            . 'Admin akan hubungi kamu langsung. Ditunggu sebentar ya 🙏';
    }

    // ── Edit listing via DM ─────────────────────────────────────────────────

    /**
     * Handle edit listing command from DM.
     * Ownership: only listing owner OR master can edit.
     */
    private function handleEditListing(Message $message, int $listingId, string $editText): string
    {
        $phone = $message->sender_number;
        $contact = Contact::where('phone_number', $phone)->first();
        $isMaster = MasterCommandAgent::isMasterPhone($phone);

        if ($listingId <= 0) {
            // No listing ID — show user's listings with IDs
            return $this->myListingsForEdit($phone);
        }

        $listing = Listing::with('category')->find($listingId);
        if (!$listing) {
            return "❌ Iklan #{$listingId} tidak ditemukan.\n\nKetik *iklanku* untuk melihat daftar iklan kamu.";
        }

        // Ownership check — master bypass
        if (!$isMaster) {
            $isOwner = ($contact && $listing->contact_id === $contact->id) ||
                $listing->contact_number === $phone;
            if (!$isOwner) {
                return "🚫 Kamu tidak bisa mengedit iklan #{$listingId} karena bukan milikmu.";
            }
        }

        // Strip "edit iklan 35" prefix from text to get pure edit instructions
        $cleanText = preg_replace('/^(?:edit|ubah|perbarui|update)\s*(?:iklan|listing|#)?\s*#?\d+\s*/iu', '', $editText);
        $cleanText = trim($cleanText ?: $editText);

        if (empty($cleanText)) {
            // No edit instructions — show current listing & wait for next message
            Cache::put('edit_pending:' . $phone, ['listing_id' => $listingId], now()->addMinutes(5));
            return $this->formatListingForEdit($listing);
        }

        return $this->parseAndApplyEdits($listing, $cleanText, $phone);
    }

    /**
     * Handle continuation of a pending edit (user sent edit instructions after seeing listing).
     */
    private function applyPendingEdit(Message $message, int $listingId, string $text): string
    {
        if (preg_match('/^\s*(batal|cancel|ga\s*jadi|tidak|no)\s*$/i', $text)) {
            return '👌 Edit dibatalkan.';
        }

        $listing = Listing::with('category')->find($listingId);
        if (!$listing) {
            return "❌ Iklan #{$listingId} tidak ditemukan.";
        }

        // Re-check ownership
        $phone = $message->sender_number;
        $contact = Contact::where('phone_number', $phone)->first();
        $isMaster = MasterCommandAgent::isMasterPhone($phone);
        if (!$isMaster) {
            $isOwner = ($contact && $listing->contact_id === $contact->id) ||
                $listing->contact_number === $phone;
            if (!$isOwner) {
                return "🚫 Kamu tidak bisa mengedit iklan #{$listingId} karena bukan milikmu.";
            }
        }

        return $this->parseAndApplyEdits($listing, $text, $phone);
    }

    /**
     * Show listing details for editing.
     */
    private function formatListingForEdit(Listing $listing): string
    {
        $cat = $listing->category?->name ?? 'Belum dikategorikan';
        $condition = match ($listing->condition) {
            'new' => 'Baru',
            'used' => 'Bekas',
            default => 'Tidak diketahui'
        };
        $status = match ($listing->status) {
            'active' => '🟢 Aktif',
            'sold' => '✅ Terjual',
            'expired' => '🟡 Kadaluarsa',
            default => '⚪ ' . $listing->status
        };

        return "📝 *Edit Iklan #{$listing->id}*\n\n"
            . "📌 *Judul:* {$listing->title}\n"
            . "💰 *Harga:* {$listing->price_formatted}\n"
            . "📂 *Kategori:* {$cat}\n"
            . '📍 *Lokasi:* ' . ($listing->location ?: '-') . "\n"
            . "📦 *Kondisi:* {$condition}\n"
            . "{$status}\n"
            . '📝 *Deskripsi:* ' . Str::limit($listing->description, 200) . "\n\n"
            . "─────────────────\n"
            . "Balas dengan perubahan yang diinginkan, contoh:\n\n"
            . "• *harga 150000*\n"
            . "• *judul Gamis Syar'i Premium*\n"
            . "• *terjual*\n"
            . "• *lokasi Jakarta Selatan*\n"
            . "• *deskripsi Gamis bahan wolfis...*\n\n"
            . '_Atau ketik *batal* untuk membatalkan._';
    }

    /**
     * Show user's listing list with IDs for edit selection.
     */
    private function myListingsForEdit(string $phoneNumber): string
    {
        $contact = Contact::where('phone_number', $phoneNumber)->first();
        if (!$contact) {
            return '❌ Nomor kamu belum terdaftar.';
        }

        $listings = Listing::where('contact_id', $contact->id)
            ->orWhere('contact_number', $phoneNumber)
            ->latest('source_date')
            ->limit(10)
            ->get();

        if ($listings->isEmpty()) {
            return '📭 Kamu belum punya iklan yang bisa diedit.';
        }

        $lines = ["📋 *Pilih Iklan untuk Diedit*\n"];
        foreach ($listings as $listing) {
            $status = match ($listing->status) {
                'active' => '🟢',
                'sold' => '✅',
                default => '🟡',
            };
            $lines[] = "{$status} *#{$listing->id}* — {$listing->title}\n   💰 {$listing->price_formatted}";
        }
        $lines[] = "\nKetik: *edit #<nomor>*\nContoh: *edit #" . $listings->first()->id . '*';

        return implode("\n", $lines);
    }

    /**
     * Parse natural-language edit instructions with Gemini and apply.
     */
    private function parseAndApplyEdits(Listing $listing, string $editText, ?string $senderPhone = null): string
    {
        // Detect "on behalf pasmal" — only allowed for master
        $onBehalfPasmal = $senderPhone
            && MasterCommandAgent::isMasterPhone($senderPhone)
            && AdBuilderAgent::isOnBehalfPasmal($editText);

        // Strip "on behalf pasmal" from editText before sending to Gemini
        $editText = trim(preg_replace('/\b(on\s*behalf\s*pasmal|atas\s*nama\s*pasmal|behalf\s*pasmal)\b[,\s]*/iu', '', $editText));
        $editText = trim($editText, ' ,');

        // If nothing left after stripping (pure "on behalf pasmal" command), skip Gemini
        if (empty($editText) && $onBehalfPasmal) {
            $pasmalPhone   = Setting::get('pasmal_contact_phone', '082211436115');
            $pasmalName    = Setting::get('pasmal_contact_name', 'Pasaramal Jamaah');
            $pasmalContact = Contact::where('phone_number', $pasmalPhone)->first();
            $listing->update([
                'contact_number' => $pasmalPhone,
                'contact_name'   => $pasmalName,
                'contact_id'     => $pasmalContact?->id,
            ]);
            $listing->refresh();
            $link = "{$this->baseUrl}/p/{$listing->id}";
            return "✅ *Iklan #{$listing->id} berhasil diperbarui!*\n\n"
                . "• Kontak → *{$pasmalName}* ({$pasmalPhone})\n\n"
                . "🔗 {$link}";
        }

        // Clean existing description of any "on behalf pasmal" contamination before passing to Gemini
        $cleanExistingDesc = trim(preg_replace('/\b(on\s*behalf\s*pasmal|atas\s*nama\s*pasmal|behalf\s*pasmal)\b[.,\s]*/iu', '', $listing->description ?? ''));

        $categories = Category::where('is_active', true)->pluck('name', 'id')->toArray();
        $catList = implode(', ', array_map(fn($id, $name) => "{$id}:{$name}", array_keys($categories), $categories));

        $prompt = "Kamu adalah parser edit iklan marketplace.\n"
            . "Data iklan saat ini:\n"
            . "- Judul: {$listing->title}\n"
            . '- Deskripsi: ' . Str::limit($cleanExistingDesc, 300) . "\n"
            . "- Harga: {$listing->price}\n"
            . "- Label harga: {$listing->price_label}\n"
            . "- Lokasi: {$listing->location}\n"
            . "- Kondisi: {$listing->condition} (new/used/unknown)\n"
            . "- Status: {$listing->status} (active/sold/expired)\n"
            . "- Kategori ID: {$listing->category_id}\n\n"
            . "Kategori tersedia: {$catList}\n\n"
            . "Instruksi edit dari user:\n\"{$editText}\"\n\n"
            . "Tentukan field mana yang ingin diubah dan nilai barunya.\n"
            . "Jawab HANYA JSON valid:\n"
            . '{"title":null,"description":null,"price":null,"price_label":null,'
            . '"price_type":null,"location":null,"condition":null,"status":null,"category_id":null}' . "\n\n"
            . "ATURAN PENTING:\n"
            . "- Hanya isi field yang user SECARA EKSPLISIT minta ubah. Field lain = null.\n"
            . "- Untuk field description: HANYA isi jika ada sesuatu yang perlu ditambahkan atau diubah. Jika diisi, WAJIB dimulai dari description_lama yang sudah ada, lalu tambahkan/perluas. JANGAN replace dengan versi lebih pendek.\n"
            . "- Jika ada frasa deskriptif tambahan seperti 'persediaan terbatas', 'stok terbatas', 'COD', 'bonus', dll: TAMBAHKAN ke akhir description yang sudah ada (append). Format: description_lama + '\\n' + frasa_baru.\n"
            . "- Jika user meminta 'tambahan deskripsi yg sesuai' / 'tambah keterangan' / 'lengkapi deskripsi': perluas description yang ada dengan detail produk yang relevan (minimal 3-4 kalimat). Gunakan context dari judul dan data iklan.\n"
            . "- Jika tidak ada instruksi terkait description sama sekali, description = null.\n"
            . "- Jika user bilang \"terjual\"/\"sudah laku\"/\"sold\" → status=\"sold\"\n"
            . "- Jika user bilang \"sembunyikan\"/\"nonaktif\" → status=\"inactive\"\n"
            . "- Jika user bilang \"aktifkan lagi\" → status=\"active\"\n"
            . "- Jika user bilang harga \"150k\"/\"1 jutaan\"/\"1jt\" → konversi ke angka (150000 / 1000000)\n"
            . "- Jika ada kata nego/negotiable → price_type=\"nego\"\n"
            . "- Jika ada kata lelang/auction → price_type=\"lelang\"\n"
            . "- Jika ada kata fix/tetap → price_type=\"fix\"\n"
            . 'price_type valid: "fix", "nego", "lelang". Default jika tidak disebutkan = null (jangan ubah).';

        $parsed = $this->gemini->generateJson($prompt);

        if (!$parsed) {
            return "⏳ *AI sedang sibuk, silakan coba beberapa saat lagi.*\n\n"
                . "_Kirim ulang perintah edit yang sama ya._";
        }

        // Strip any "on behalf pasmal" that Gemini may have included in description
        if (!empty($parsed['description'])) {
            $parsed['description'] = trim(preg_replace('/\b(on\s*behalf\s*pasmal|atas\s*nama\s*pasmal|behalf\s*pasmal)\b[.,\s]*/iu', '', $parsed['description']));
        }

        // Safety guard: reject description if Gemini replaced it with something shorter than the clean original
        if (!empty($parsed['description']) && !empty($cleanExistingDesc)) {
            if (mb_strlen($parsed['description']) < mb_strlen($cleanExistingDesc)) {
                $parsed['description'] = null;
            }
        }

        $changes = [];
        $changeDesc = [];
        $editable = ['title', 'description', 'price', 'price_label', 'price_type', 'location', 'condition', 'status', 'category_id'];

        foreach ($editable as $field) {
            if (!isset($parsed[$field]) || $parsed[$field] === null) {
                continue;
            }
            $val = $parsed[$field];

            // Validate enum fields
            if ($field === 'condition' && !in_array($val, ['new', 'used', 'unknown']))
                continue;
            if ($field === 'status' && !in_array($val, ['active', 'sold', 'expired', 'inactive']))
                continue;
            if ($field === 'price_type' && !in_array($val, ['fix', 'nego', 'lelang']))
                continue;
            if ($field === 'category_id' && !isset($categories[$val]))
                continue;

            $changes[$field] = $val;

            $label = match ($field) {
                'title' => 'Judul',
                'description' => 'Deskripsi',
                'price' => 'Harga',
                'price_label' => 'Label harga',
                'location' => 'Lokasi',
                'condition' => 'Kondisi',
                'status' => 'Status',
                'price_type' => 'Tipe Harga',
                'category_id' => 'Kategori',
                default => $field,
            };
            $display = match ($field) {
                'price' => 'Rp ' . number_format((int) $val, 0, ',', '.'),
                'category_id' => $categories[$val] ?? $val,
                'condition' => match ($val) {
                    'new' => 'Baru',
                    'used' => 'Bekas',
                    default => 'Tidak diketahui'
                },
                'status' => match ($val) {
                    'active' => 'Aktif',
                    'sold' => 'Terjual',
                    'expired' => 'Kadaluarsa',
                    'inactive' => 'Disembunyikan',
                    default => $val
                },
                'price_type' => match ($val) {
                    'nego' => 'Nego 🤝',
                    'lelang' => 'Lelang 🔨',
                    default => 'Fix 🏷️',
                },
                default => Str::limit((string) $val, 100),
            };
            $changeDesc[] = "• {$label} → *{$display}*";
        }

        // On behalf Pasmal: always update contact info, regardless of other changes
        if ($onBehalfPasmal) {
            $pasmalPhone   = Setting::get('pasmal_contact_phone', '082211436115');
            $pasmalName    = Setting::get('pasmal_contact_name', 'Pasaramal Jamaah');
            $pasmalContact = Contact::where('phone_number', $pasmalPhone)->first();
            $listing->update([
                'contact_number' => $pasmalPhone,
                'contact_name'   => $pasmalName,
                'contact_id'     => $pasmalContact?->id,
            ]);
            // Don't add kontak to changeDesc — contact info is visible on the website
        }

        if (empty($changes) && !$onBehalfPasmal) {
            return "🤔 Tidak ada perubahan yang terdeteksi dari:\n_\"{$editText}\"_\n\n"
                . "Contoh yang dimengerti:\n"
                . "• *harga lelang mulai 1 juta*\n"
                . "• *harga nego 500000*\n"
                . "• *harga fix 750000*\n"
                . "• *terjual*\n"
                . "• *judul [judul baru]*\n"
                . "• *lokasi Bekasi*";
        }

        // Mutual exclusion: price vs price_label
        if (isset($changes['price']) && $changes['price'] > 0) {
            $changes['price_label'] = null;
        } elseif (isset($changes['price_label'])) {
            $changes['price'] = null;
        }

        if (!empty($changes)) {
            $listing->update($changes);
        }

        $listing->refresh();

        $link = "{$this->baseUrl}/p/{$listing->id}";
        $result = "✅ *Iklan #{$listing->id} berhasil diperbarui!*\n\n"
            . implode("\n", $changeDesc) . "\n\n"
            . "🔗 {$link}";

        $group = \App\Models\WhatsappGroup::where('is_active', true)->first();

        // Status sold → announce terjual, stop here (no re-post)
        if (($changes['status'] ?? '') === 'sold') {
            if ($group) {
                $wagMsg = "✅ *TERJUAL!*\n\n"
                    . "📦 *{$listing->title}* (#️⃣{$listing->id})\n"
                    . "👤 Penjual: " . ($listing->contact_name ?? 'Penjual') . "\n\n"
                    . "_Iklan ini sudah tidak tersedia._";
                try {
                    $this->whacenter->sendGroupMessage($group->group_name, $wagMsg);
                } catch (\Exception $e) {
                    Log::warning('parseAndApplyEdits: gagal kirim notif terjual ke WAG', ['error' => $e->getMessage()]);
                }
            }
            $result .= "\n\n📭 _Iklan sudah dihapus dari etalase marketplace._";
            return $result;
        }

        // Status inactive/hidden → no WAG post
        if (($changes['status'] ?? '') === 'inactive') {
            $result .= "\n\n📭 _Iklan disembunyikan dari etalase._";
            return $result;
        }

        // For all other updates (harga, judul, dll) → re-post to WAG with clean image caption
        if ($group && $listing->status === 'active') {
            $priceStr    = $listing->price_formatted;
            $catLine     = $listing->category ? "📂 {$listing->category->name}\n" : '';
            $locLine     = $listing->location ? "📍 {$listing->location}\n" : '';
            $mediaUrls   = $listing->media_urls ?? [];
            $firstImage  = !empty($mediaUrls) ? $mediaUrls[0] : null;
            $shortDesc   = $listing->description ? Str::limit(explode("\n", $listing->description)[0], 120) : '';
            $descLine    = $shortDesc ? "_{$shortDesc}_\n" : '';

            $wagCaption = "✏️ *[UPDATE] {$listing->title}*\n"
                . $descLine
                . "💰 {$priceStr}\n"
                . $catLine
                . $locLine
                . "\n🔗 {$link}";

            try {
                if ($firstImage) {
                    $this->whacenter->sendGroupImageMessage($group->group_name, $wagCaption, $firstImage);
                } else {
                    $this->whacenter->sendGroupMessage($group->group_name, $wagCaption);
                }
            } catch (\Exception $e) {
                Log::warning('parseAndApplyEdits: gagal re-post ke WAG', ['error' => $e->getMessage()]);
            }
        }

        return $result;
    }

    /**
     * Parse [UPDATE:field=value,...] string and update contact record.
     */
    private function applyContactUpdate(string $phoneNumber, string $updateStr): void
    {
        $contact = Contact::where('phone_number', $phoneNumber)->first();
        if (!$contact)
            return;

        $updates = [];
        $pairs = explode(',', $updateStr);
        $allowedFields = ['name', 'address', 'sell_products', 'buy_products'];

        foreach ($pairs as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2) {
                $field = trim($parts[0]);
                $value = trim($parts[1]);
                if (in_array($field, $allowedFields) && !empty($value)) {
                    $updates[$field] = $value;
                }
            }
        }

        if (!empty($updates)) {
            $contact->update($updates);
            Log::info("BotQueryAgent: auto-updated contact {$phoneNumber}", $updates);
        }
    }
}
