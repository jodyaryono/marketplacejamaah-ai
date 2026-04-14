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
use Illuminate\Support\Facades\Log;

/**
 * BotQueryAgent — thin orchestrator for DM commands.
 *
 * Heavy logic lives in dedicated sub-agents:
 *   SearchAgent      — product / seller search + RAG
 *   LocationAgent    — GPS search & business location update
 *   KtpScanAgent     — ID-card photo scanning
 *   ListingEditAgent — edit / mark sold / reactivate
 *   AdBuilderAgent   — multi-step ad creation
 */
class BotQueryAgent
{
    private string $baseUrl;
    private AdBuilderAgent $adBuilder;

    public function __construct(
        private WhacenterService $whacenter,
        private GeminiService $gemini,
        private SearchAgent $search,
        private LocationAgent $location,
        private KtpScanAgent $ktp,
        private ListingEditAgent $listingEdit,
    ) {
        $this->baseUrl  = rtrim(config('app.url'), '/');
        $this->adBuilder = app(AdBuilderAgent::class);
    }

    /**
     * Handle a DM from a registered member.
     * Returns true if the message was handled, false otherwise.
     */
    public function handle(Message $message): bool
    {
        $log = AgentLog::create([
            'agent_name' => 'BotQueryAgent',
            'message_id' => $message->id,
            'status'     => 'processing',
        ]);

        try {
            // ── 1. Location message ───────────────────────────────────────────
            if ($message->message_type === 'location') {
                return $this->handleLocationMessage($message, $log);
            }

            // ── 2. Image DM ───────────────────────────────────────────────────
            if ($message->message_type === 'image') {
                return $this->handleImageMessage($message, $log);
            }

            // ── 3. Active ad-builder session (text) ───────────────────────────
            $adBuilderState = $this->adBuilder->getState($message->sender_number);
            if ($adBuilderState) {
                $text  = trim($message->raw_body ?? '');
                $step  = $adBuilderState['step'] ?? '';
                $reply = match ($step) {
                    'waiting_input' => $this->adBuilder->handleTextWhileWaiting($message->sender_number, $text),
                    'enriching'     => $this->adBuilder->handleEnriching($message->sender_number, $text, $adBuilderState),
                    'reviewing'     => $this->adBuilder->handleReview($message, $adBuilderState),
                    default         => $this->adBuilder->handleTextWhileWaiting($message->sender_number, $text),
                };
                if ($reply !== '') {
                    $this->whacenter->sendMessage($message->sender_number, $reply);
                }
                $log->update(['status' => 'success', 'output_payload' => ['intent' => 'ad_builder_text', 'step' => $step]]);
                return true;
            }

            // ── 4. Sticker / media without text ──────────────────────────────
            $text = trim($message->raw_body ?? '');
            if (empty($text)) {
                if (in_array($message->message_type, ['sticker', 'audio', 'video'])) {
                    $contact = Contact::where('phone_number', $message->sender_number)->first();
                    $name    = $contact ? $contact->getSapaan($message->sender_name) : ($message->sender_name ?: 'Kak');
                    $options = ['😄👍', "Hehe lucu *{$name}* 😂", '😊🙏',
                        "Wkwk 😆 ada yang bisa aku bantu *{$name}*? Ketik *bantuan* ya 🙏",
                        "🤣👍 Btw, butuh bantuan apa *{$name}*?"];
                    $this->whacenter->sendMessage($message->sender_number, $options[array_rand($options)]);
                    $log->update(['status' => 'success', 'output_payload' => ['intent' => 'sticker_reply']]);
                    return true;
                }
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'empty_text']]);
                return false;
            }

            // ── 5. Pending location-intent answer (reply to "1" or "2") ──────
            $locKey = 'loc_pending:' . $message->sender_number;
            if (Cache::has($locKey) && preg_match('/^\s*[12]\s*$/', $text)) {
                $pending = Cache::pull($locKey);
                $lat = $pending['lat'];
                $lng = $pending['lng'];
                $reply = trim($text) === '1'
                    ? $this->location->handleUpdateBusinessLocation($lat, $lng, $message->sender_number)
                    : $this->location->handleLocationSearch($lat, $lng, $message->sender_number);
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $intent = trim($text) === '1' ? 'update_location' : 'location_search';
                $log->update(['status' => 'success', 'output_payload' => compact('intent', 'lat', 'lng')]);
                return true;
            }

            // ── 6. Quick regex shortcuts (bypass Gemini) ─────────────────────

            // Mark as sold: "terjual #146"
            if (preg_match('/\b(?:terjual|laku|sold|tandai\s+(?:terjual|laku|sold))\b.*?#?(\d+)|#?(\d+).*?\b(?:terjual|laku|sold)\b/iu', $text, $m)) {
                $id = (int) ($m[1] ?: $m[2]);
                if ($id > 0) {
                    $reply = $this->listingEdit->markAsSold($message, $id);
                    $this->whacenter->sendMessage($message->sender_number, $reply);
                    $log->update(['status' => 'success', 'output_payload' => ['intent' => 'mark_sold', 'listing_id' => $id]]);
                    return true;
                }
            }

            // Reactivate: "aktifkan #146"
            if (preg_match('/\b(?:aktifkan|aktifkan\s+kembali|aktif|reactivate)\b.*?#?(\d+)|#?(\d+).*?\b(?:aktifkan|aktifkan\s+kembali)\b/iu', $text, $m)) {
                $id = (int) ($m[1] ?: $m[2]);
                if ($id > 0) {
                    $reply = $this->listingEdit->reactivateListing($message, $id);
                    $this->whacenter->sendMessage($message->sender_number, $reply);
                    $log->update(['status' => 'success', 'output_payload' => ['intent' => 'reactivate_listing', 'listing_id' => $id]]);
                    return true;
                }
            }

            // Create ad
            if (preg_match('/\b(buat|pasang|bikin|tambah|post|posting)\s+(iklan|jualan|dagangan|produk|listing)\b|\b(iklan\s+baru|pasang\s+iklan|buat\s+iklan|iklan\s+via\s+bot)\b/iu', $text)) {
                $contact = Contact::where('phone_number', $message->sender_number)->first();
                $name    = $contact ? $contact->getSapaan($message->sender_name) : ($message->sender_name ?: 'Kak');
                $reply   = $this->adBuilder->start($message->sender_number, $name);
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $log->update(['status' => 'success', 'output_payload' => ['intent' => 'create_ad']]);
                return true;
            }

            // KTP scan
            if (preg_match('/\b(scan|baca|ocr|ekstrak|upload|foto|kirim|cek|check|identitas)\s+k\.?t\.?p\.?\b|\bk\.?t\.?p\.?\s*(scan|baca|ocr|upload|kirim)\b|^\s*k\.?t\.?p\.?\s*$/i', $text)) {
                $reply = $this->ktp->requestScan($message->sender_number);
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $log->update(['status' => 'success', 'output_payload' => ['intent' => 'scan_ktp']]);
                return true;
            }

            // Pending edit continuation
            $editKey = 'edit_pending:' . $message->sender_number;
            if (Cache::has($editKey)) {
                $pending  = Cache::pull($editKey);
                $memberOnly = $pending['member_only'] ?? false;
                $reply = $memberOnly
                    ? $this->listingEdit->applyMemberEdit($message, $pending['listing_id'], $text)
                    : $this->listingEdit->applyPendingEdit($message, $pending['listing_id'], $text);
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $log->update(['status' => 'success', 'output_payload' => ['intent' => 'edit_listing_continue', 'listing_id' => $pending['listing_id']]]);
                return true;
            }

            // Bare listing number → quick member edit menu
            if (preg_match('/^\s*#?(\d{1,6})\s*$/', $text, $m)) {
                $id      = (int) $m[1];
                $contact = Contact::where('phone_number', $message->sender_number)->first();
                if ($contact && $id > 0) {
                    $listing = Listing::where('id', $id)
                        ->where(fn($q) => $q->where('contact_id', $contact->id)
                            ->orWhere('contact_number', $message->sender_number))
                        ->first();
                    if ($listing) {
                        $reply = $this->listingEdit->showMemberEditMenu($listing, $message->sender_number);
                        $this->whacenter->sendMessage($message->sender_number, $reply);
                        $log->update(['status' => 'success', 'output_payload' => ['intent' => 'member_quick_edit', 'listing_id' => $id]]);
                        return true;
                    }
                }
            }

            // Explicit edit command: "edit #35 harga 200000"
            if (preg_match('/^(?:edit|ubah|perbarui|update)\s*(?:iklan|listing|#)?\s*#?(\d+)\s*(.*)/iu', $text, $m)) {
                $reply = $this->listingEdit->handleEditListing($message, (int) $m[1], trim($m[2]));
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $log->update(['status' => 'success', 'output_payload' => ['intent' => 'edit_listing', 'listing_id' => (int) $m[1]]]);
                return true;
            }

            // ── 7. Clarify context injection ──────────────────────────────────
            $clarifyKey = 'clarify_pending:' . $message->sender_number;
            $clarifyContext = null;
            if (Cache::has($clarifyKey)) {
                $clarifyContext = Cache::pull($clarifyKey);
                $text = "Pesan sebelumnya: \"{$clarifyContext['original']}\" → Klarifikasi user: \"{$text}\"";
            }

            // ── 8. Gemini intent detection ────────────────────────────────────
            $categoryNames  = Category::where('is_active', true)->pluck('name')->implode(', ');
            $promptTemplate = Setting::get('prompt_bot_intent', 'Deteksi intent: {text} Kategori: {categoryNames}');
            $prompt         = str_replace(['{text}', '{categoryNames}'], [$text, $categoryNames], $promptTemplate);

            // Cache intent results for 5 min — same phrasing → same intent
            $parsed = $this->gemini->generateJson($prompt, cacheTtl: 5) ?? $this->fallbackParse($text);

            $intent        = $parsed['intent'] ?? 'unknown';
            $categoryQuery = $parsed['category_query'] ?? null;
            $limit         = min((int) ($parsed['limit'] ?? 5), 10);
            $clarifyQ      = $parsed['clarify_question'] ?? null;

            // Greeting/conversation mentioning price keywords → treat as product search
            if (in_array($intent, ['greeting', 'conversation', 'general_question', 'unknown'])) {
                if (preg_match('/\b(harga|berapa|brp|ada\s+jual|jual\s+apa|dijual|ada\s+yang\s+jual|mau\s+beli|cari)\b/i', $text)) {
                    $intent = 'search_product';
                    if (!($parsed['keyword'] ?? null)) {
                        $kw = preg_replace('/^(assalamu\S*\s*,?\s*|wa.?alaikum\S*\s*,?\s*|halo\s*,?\s*|hai\s*,?\s*|bismillah\s*,?\s*)/iu', '', $text);
                        $kw = preg_replace('/\b(harga|berapa|brp|ya|dong|deh|nih|ada|jual|mau\s+beli|cari)\b\s*/iu', ' ', $kw);
                        $parsed['keyword'] = trim(preg_replace('/\s+/', ' ', $kw)) ?: null;
                    }
                }
            }

            $reply = match ($intent) {
                'search_seller'  => $this->search->searchSellers($text, $categoryQuery, $limit),
                'search_product' => $this->search->searchProducts($text, $categoryQuery, $limit),
                'list_categories'=> $this->search->listCategories(),
                'my_listings'    => $this->search->myListings($message->sender_number, $limit),
                'edit_listing'   => $this->listingEdit->handleEditListing($message, (int) ($parsed['listing_id'] ?? 0), $text),
                'create_ad'      => $this->handleCreateAd($message),
                'help'           => $this->helpMessage(),
                'off_topic'      => $this->offTopicReply(),
                'contact_admin'  => $this->handleContactAdmin($message->sender_number),
                'scan_ktp'       => $this->ktp->requestScan($message->sender_number),
                'clarify'        => $this->handleClarify($clarifyContext['original'] ?? $text, $message->sender_number, $clarifyQ),
                default          => $this->handleConversation($text, $message->sender_number),
            };

            $this->whacenter->sendMessage($message->sender_number, $reply);
            $log->update(['status' => 'success', 'output_payload' => compact('intent', 'categoryQuery')]);
            return true;

        } catch (\Exception $e) {
            Log::error('BotQueryAgent failed', ['error' => $e->getMessage()]);
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return false;
        }
    }

    // ── Private handlers ─────────────────────────────────────────────────────

    private function handleLocationMessage(Message $message, AgentLog $log): bool
    {
        $p   = $message->raw_payload ?? [];
        $lat = isset($p['location']['latitude'])  ? (float) $p['location']['latitude']  : (isset($p['latitude'])  ? (float) $p['latitude']  : null);
        $lng = isset($p['location']['longitude']) ? (float) $p['location']['longitude'] : (isset($p['longitude']) ? (float) $p['longitude'] : null);

        if ($lat !== null && $lng !== null) {
            Cache::put('loc_pending:' . $message->sender_number, ['lat' => $lat, 'lng' => $lng], now()->addMinutes(5));
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

    private function handleImageMessage(Message $message, AgentLog $log): bool
    {
        $mediaUrl  = $message->media_url;
        $mediaData = ($message->raw_payload ?? [])['media_data'] ?? null;

        if (!$mediaUrl && !$mediaData) {
            return false;
        }

        // KTP pending?
        $ktpKey = 'ktp_pending:' . $message->sender_number;
        if (Cache::has($ktpKey)) {
            Cache::forget($ktpKey);
            $this->whacenter->sendMessage($message->sender_number, '⏳ _Sedang membaca gambar, mohon tunggu sebentar..._');
            $reply = ($mediaData && !$mediaUrl)
                ? $this->ktp->analyzeBase64($mediaData['data'], $mediaData['mimetype'] ?? 'image/jpeg')
                : $this->ktp->analyzeFromUrl($mediaUrl);
            $this->whacenter->sendMessage($message->sender_number, $reply);
            $log->update(['status' => 'success', 'output_payload' => ['intent' => 'ktp_scan_image']]);
            return true;
        }

        // Ad builder: active session or auto-start
        if (!$this->adBuilder->getState($message->sender_number)) {
            $this->adBuilder->startSilent($message->sender_number);
        }
        $reply = $this->adBuilder->handleImage($message);
        if ($reply !== '') {
            $this->whacenter->sendMessage($message->sender_number, $reply);
        }
        $log->update(['status' => 'success', 'output_payload' => ['intent' => 'ad_builder_image']]);
        return true;
    }

    private function handleCreateAd(Message $message): string
    {
        $contact        = Contact::where('phone_number', $message->sender_number)->first();
        $name           = $contact ? $contact->getSapaan($message->sender_name) : ($message->sender_name ?: 'Kak');
        $isMaster       = MasterCommandAgent::isMasterPhone($message->sender_number);
        $onBehalfPasmal = $isMaster && AdBuilderAgent::isOnBehalfPasmal($message->raw_body ?? $message->content ?? '');
        return $this->adBuilder->start($message->sender_number, $name, $onBehalfPasmal);
    }

    private function handleConversation(string $text, string $phoneNumber): string
    {
        $contact  = Contact::where('phone_number', $phoneNumber)->first();
        $name     = $contact ? $contact->getSapaan() : 'Kak';
        $prevCount = \App\Models\Message::where('sender_number', $phoneNumber)->count();

        $totalListings = \App\Models\Listing::where('status', 'active')->count();
        $totalSellers  = Contact::where('is_registered', true)
            ->whereIn('member_role', ['seller', 'both'])->count();
        $categoryNames = Category::where('is_active', true)->pluck('name')->implode(', ');

        $memberContext = $contact?->is_registered
            ? 'Member terdaftar (penjual/pembeli aktif di marketplace)'
            : ($contact ? 'Anggota grup marketplace (belum daftar formal via bot)' : 'Pengguna baru');

        $greetingRule = $prevCount <= 1
            ? 'Ini adalah pesan PERTAMA dari member, boleh membuka dengan salam singkat (maks 1 baris).'
            : 'Ini BUKAN pesan pertama. JANGAN tambahkan salam, sapaan, atau basa-basi pembuka. Langsung jawab.';

        $promptTemplate = Setting::get('prompt_bot_conversation', 'Admin Marketplace Jamaah. Pesan: "{text}". Balas natural.');
        $prompt = str_replace(
            ['{text}', '{memberContext}', '{name}', '{totalListings}', '{totalSellers}', '{categoryNames}', '{baseUrl}', '{greetingRule}'],
            [$text, $memberContext, $name, $totalListings, $totalSellers, $categoryNames, $this->baseUrl, $greetingRule],
            $promptTemplate
        );

        $reply = $this->gemini->generateContent($prompt);

        if (!$reply || strtoupper(trim($reply)) === 'OFFTOPIC' || stripos(trim($reply), 'OFFTOPIC') === 0) {
            return $this->offTopicReply();
        }

        if (preg_match('/\[UPDATE:(.+?)\]/', $reply, $m)) {
            $reply = trim(preg_replace('/\[UPDATE:.+?\]/', '', $reply));
            $this->applyContactUpdate($phoneNumber, $m[1]);
        }

        return $reply ?: "Maaf, saya tidak mengerti pesan kamu. Bisa dijelaskan lebih detail? 🙏\n\nKetik *bantuan* untuk lihat fitur tersedia.";
    }

    private function handleClarify(string $originalText, string $phone, ?string $question): string
    {
        Cache::put('clarify_pending:' . $phone, ['original' => $originalText], now()->addMinutes(5));

        $default = "🤔 Bisa diperjelas maksudnya?\n\n"
            . "Misalnya:\n"
            . "• *Cari produk* → \"cari gamis ukuran L\"\n"
            . "• *Cari penjual* → \"siapa yang jual makanan?\"\n"
            . "• *Info iklan saya* → \"iklanku ada apa?\"\n\n"
            . '_Atau ketik *bantuan* untuk lihat semua fitur._';

        return $question
            ? "🤔 {$question}\n\n_Atau ketik *bantuan* untuk lihat semua fitur._"
            : $default;
    }

    private function handleContactAdmin(string $senderPhone): string
    {
        $adminPhone = config('services.wa_gateway.admin_phone', '6285719195627');
        $contact    = Contact::where('phone_number', $senderPhone)->first();
        $name       = $contact?->name ?? $senderPhone;

        $this->whacenter->sendMessage(
            $adminPhone,
            "📩 *Pesan dari member:*\n\nNama: *{$name}*\nNo: {$senderPhone}\n\n"
                . 'Member ini mau bicara/ketemu admin. Silakan hubungi langsung ya 🙏'
        );

        return "Oke *{$name}*, udah aku sampaikan ke admin ya! 😊\n\nAdmin akan hubungi kamu langsung. Ditunggu sebentar ya 🙏";
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

    private function offTopicReply(): string
    {
        return "Untuk itu aku belum bisa bantu ya — fokusnya soal jual beli di Marketplace Jamaah 🛒\n\n"
            . 'Ketik *bantuan* untuk lihat fitur lengkapnya 👇';
    }

    private function fallbackParse(string $text): array
    {
        $lower = mb_strtolower($text);

        if (preg_match('/\b(assalamu|wa.?alaikum|halo|hai|\bhi\b|selamat\s+(pagi|siang|sore|malam)|apa kabar|permisi)\b/i', $lower))
            return ['intent' => 'greeting', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        if (preg_match('/\b(kategori|daftar|list)\b/', $lower))
            return ['intent' => 'list_categories', 'category_query' => null, 'keyword' => null, 'limit' => 20];
        if (preg_match('/\b(iklan(ku)?|jualanku|produkku|daganganku)\b/', $lower))
            return ['intent' => 'my_listings', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        if (preg_match('/\b(edit|ubah|perbarui|update)\s*(iklan|listing|#)?\s*#?(\d+)/i', $lower, $m))
            return ['intent' => 'edit_listing', 'listing_id' => (int) $m[3], 'category_query' => null, 'keyword' => null, 'limit' => 5];
        if (preg_match('/\b(buat|pasang|bikin|tambah|post|posting)\s+(iklan|jualan|dagangan|produk|listing)\b|\b(iklan\s+baru|pasang\s+iklan|buat\s+iklan)\b/iu', $lower))
            return ['intent' => 'create_ad', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        if (preg_match('/\b(bantuan|help|info|apa yang|bisa apa)\b/', $lower))
            return ['intent' => 'help', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        if (preg_match('/k\.?t\.?p\.?/i', $lower))
            return ['intent' => 'scan_ktp', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        if (preg_match('/\b(admin|pembuat|developer|yang buat|mau ketemu|bicara|hubungi|kontak)\b.*\b(admin|bot|sistem|manusia|asli)\b|\b(admin|bot|sistem)\b.*\b(siapa|buat|bikin|ketemu|bicara|hubungi)\b/i', $lower))
            return ['intent' => 'contact_admin', 'category_query' => null, 'keyword' => null, 'limit' => 5];
        if (preg_match('/\b(penjual|seller|toko|jualan|jual)\b/', $lower)) {
            $kw = preg_replace('/^.*(penjual|seller|toko|jual)\s*/i', '', $text);
            return ['intent' => 'search_seller', 'category_query' => trim($kw) ?: null, 'keyword' => null, 'limit' => 5];
        }
        if (preg_match('/\b(harga|berapa|brp)\b/i', $lower)) {
            $kw = preg_replace('/^(assalamu\S*\s*,?\s*|wa.?alaikum\S*\s*,?\s*|halo\s*,?\s*|hai\s*,?\s*)/iu', '', $text);
            $kw = preg_replace('/\b(harga|berapa|brp|ya|dong|deh|nih|ada|jual)\b\s*/iu', ' ', $kw);
            return ['intent' => 'search_product', 'category_query' => null, 'keyword' => trim(preg_replace('/\s+/', ' ', $kw)) ?: null, 'limit' => 5];
        }
        if (preg_match('/\b(cari|tampilkan|ada|produk|barang|beli)\b/', $lower)) {
            $kw = preg_replace('/^.*(cari|tampilkan|ada|produk|barang|beli)\s*/i', '', $text);
            return ['intent' => 'search_product', 'category_query' => null, 'keyword' => trim($kw) ?: null, 'limit' => 5];
        }

        return ['intent' => 'conversation', 'category_query' => null, 'keyword' => null, 'limit' => 5];
    }

    private function applyContactUpdate(string $phoneNumber, string $updateStr): void
    {
        $contact = Contact::where('phone_number', $phoneNumber)->first();
        if (!$contact) return;

        $allowed = ['name', 'address', 'sell_products', 'buy_products'];
        $updates = [];
        foreach (explode(',', $updateStr) as $pair) {
            [$field, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $field = trim($field);
            $value = trim($value);
            if (in_array($field, $allowed) && $value !== '') {
                $updates[$field] = $value;
            }
        }

        if (!empty($updates)) {
            $contact->update($updates);
            Log::info("BotQueryAgent: auto-updated contact {$phoneNumber}", $updates);
        }
    }
}
