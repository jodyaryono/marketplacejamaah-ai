<?php

namespace App\Agents;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Setting;
use App\Models\WhatsappGroup;
use App\Services\GeminiService;
use App\Services\WhacenterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdBuilderAgent
{
    private const CACHE_TTL_MINUTES = 30;
    private const CACHE_PREFIX = 'ad_builder:';

    public function __construct(
        private GeminiService $gemini,
        private WhacenterService $whacenter,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Retrieve current ad-builder state for a phone, or null if not in progress.
     */
    public function getState(string $phone): ?array
    {
        return Cache::get(self::CACHE_PREFIX . $phone);
    }

    /**
     * Check if text contains "on behalf pasmal" / "atas nama pasmal" instruction.
     */
    public static function isOnBehalfPasmal(string $text): bool
    {
        return (bool) preg_match('/\b(on\s*behalf\s*pasmal|atas\s*nama\s*pasmal|behalf\s*pasmal)\b/iu', $text);
    }

    /**
     * Start the ad builder flow. Call when user says "buat iklan" etc.
     */
    public function start(string $phone, string $name, bool $onBehalfPasmal = false): string
    {
        $state = ['step' => 'waiting_input'];
        if ($onBehalfPasmal) {
            $state['on_behalf_pasmal'] = true;
        }
        Cache::put(self::CACHE_PREFIX . $phone, $state, now()->addMinutes(self::CACHE_TTL_MINUTES));

        $onBehalfNote = $onBehalfPasmal ? "\n\n_📋 Mode: atas nama *Pasmal*_" : '';

        return "🛍️ *Buat Iklan Baru*\n\n"
            . "Halo *{$name}*! Mari buat iklan yang menarik bersama AI 🤖\n\n"
            . "Caranya mudah:\n"
            . "1️⃣ Kirim *foto produk* kamu\n"
            . "2️⃣ AI otomatis buat draft iklan yang profesional\n"
            . "3️⃣ Review → setujui → iklan tayang di grup!\n\n"
            . "📸 *Silakan kirim foto produk sekarang!*\n\n"
            . "_Sertakan detail di caption (opsional): nama, harga, kondisi, lokasi_\n\n"
            . "Ketik *batal* untuk membatalkan."
            . $onBehalfNote;
    }

    /**
     * Silently start ad builder state without sending a welcome message.
     * Used when a photo arrives in DM without a prior "buat iklan" trigger.
     */
    public function startSilent(string $phone): void
    {
        Cache::put(self::CACHE_PREFIX . $phone, ['step' => 'waiting_input'], now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    /**
     * Handle an image DM when user is in ad_builder mode (any step).
     * Analyzes the image with Gemini, generates a polished draft, and shows preview.
     */
    public function handleImage(Message $message): string
    {
        $phone = $message->sender_number;
        $rawPayload = $message->raw_payload ?? [];
        $mediaUrl = $message->media_url;
        $mediaData = $rawPayload['media_data'] ?? null;
        $caption = trim($message->raw_body ?? '');

        if (!$mediaUrl && !$mediaData) {
            return '❌ Gagal menerima gambar. Silakan coba kirim ulang foto produkmu.';
        }

        $this->whacenter->sendMessage($phone, '⏳ _Sedang menganalisa gambar dan menyiapkan draft iklan..._');

        try {
            if ($mediaData && !$mediaUrl) {
                $base64 = $mediaData['data'];
                $mimeType = $mediaData['mimetype'] ?? 'image/jpeg';
            } else {
                $imageData = Http::timeout(15)->get($mediaUrl);
                if ($imageData->failed()) {
                    return '❌ Gagal mengunduh gambar. Silakan coba kirim ulang.';
                }
                $base64 = base64_encode($imageData->body());
                $mimeType = $imageData->header('Content-Type') ?: 'image/jpeg';
            }

            $categories = Category::where('is_active', true)->pluck('name')->implode(', ');

            $promptTemplate = Setting::get('prompt_ad_builder_polish',
                'Kamu adalah AI copywriter marketing untuk *Marketplace Jamaah* — komunitas jual-beli Muslim Indonesia. '
                . 'Tugasmu: ubah foto + catatan penjual menjadi iklan yang MENJUAL, ramah, dan dapat dipercaya. '
                . "\n\n"
                . 'Detail dari penjual: "{caption}". '
                . 'Kategori tersedia: {categories}. '
                . "\n\n"
                . 'PRINSIP COPYWRITING (WAJIB):' . "\n"
                . '1. Title — Hook + clarity. Sebut produk + 1 keunggulan utama. Max 60 karakter. Hindari ALL CAPS. Boleh 1 emoji relevan di awal.' . "\n"
                . '2. Description — Pakai struktur AIDA dalam 3-5 kalimat:' . "\n"
                . '   • Kalimat 1: Hook yang menyentuh manfaat / problem solving (bukan sekadar nama produk).' . "\n"
                . '   • Kalimat 2-3: Spesifikasi konkret (ukuran, bahan, warna, fitur) + 1-2 benefit nyata bagi user (bukan fitur kosong).' . "\n"
                . '   • Kalimat 4: Bukti / kondisi / kelengkapan untuk membangun trust ("masih mulus", "fullset", "garansi pribadi", dll).' . "\n"
                . '   • Kalimat 5 (opsional): Soft CTA halal — contoh "Cocok untuk hadiah", "Stok terbatas, japri sekarang ya".' . "\n"
                . '3. Bahasa: Indonesia santai-profesional. Boleh sapaan "Kak" / "Sahabat Jamaah". Hindari clickbait, hindari klaim berlebihan, hindari kata "termurah" tanpa dasar.' . "\n"
                . '4. Halal-aware: Jangan promosikan riba, judi, alkohol, produk haram. Jika gambar mengandung itu, set title kosong dan notes berisi peringatan.' . "\n"
                . '5. Emoji: maksimal 2-3 di description, hanya yang relevan (📏 ukuran, 🎁 hadiah, ✨ baru, 🔄 second, dll). Jangan spam.' . "\n"
                . '6. Harga: extract HANYA jika eksplisit di caption atau gambar. Jangan menebak. Jika tidak ada → price=0, price_label="".' . "\n"
                . "\n"
                . 'Jawab HANYA dengan JSON valid (tanpa markdown, tanpa teks lain):' . "\n"
                . '{"title":"Judul marketing maks 60 karakter",' . "\n"
                . ' "description":"Deskripsi AIDA 3-5 kalimat sesuai aturan di atas",' . "\n"
                . ' "price":0,' . "\n"
                . ' "price_label":"",' . "\n"
                . ' "price_type":"fix|nego|lelang — deteksi dari caption, default fix",' . "\n"
                . ' "category":"Pilih satu dari kategori tersedia",' . "\n"
                . ' "condition":"baru atau bekas",' . "\n"
                . ' "location":"Lokasi dari caption atau kosong",' . "\n"
                . ' "notes":"Saran ke penjual jika ada info penting yang masih kurang (mis. ukuran/lokasi/kontak), atau kosong"}'
            );

            $prompt = str_replace(
                ['{caption}', '{categories}'],
                [$caption ?: 'tidak ada keterangan dari penjual', $categories],
                $promptTemplate
            );

            $result = $this->gemini->analyzeImageWithText($base64, $mimeType, $prompt);
            if (!$result) {
                return '❌ AI gagal menganalisa gambar. Silakan coba lagi atau ketik *batal*.';
            }

            $clean = preg_replace('/```json\s*/i', '', $result);
            $clean = preg_replace('/```\s*/i', '', $clean);
            $draft = json_decode(trim($clean), true);

            if (!$draft || empty($draft['title'])) {
                Log::warning('AdBuilderAgent: invalid JSON from Gemini', ['raw' => $result]);
                return '❌ AI gagal membuat draft iklan. Coba kirim foto yang lebih jelas, atau ketik *batal*.';
            }

            $draft['media_url'] = $mediaUrl;

            // Preserve on_behalf_pasmal flag from previous state
            $currentState = Cache::get(self::CACHE_PREFIX . $phone, []);
            $newState = ['step' => 'enriching', 'draft' => $draft];
            if (!empty($currentState['on_behalf_pasmal'])) {
                $newState['on_behalf_pasmal'] = true;
            }
            // Also detect "on behalf pasmal" in the photo caption
            if (!empty($caption) && self::isOnBehalfPasmal($caption)) {
                $newState['on_behalf_pasmal'] = true;
            }

            // Save draft and go to 'enriching' step — ask for extra details before finalizing
            Cache::put(self::CACHE_PREFIX . $phone, $newState, now()->addMinutes(self::CACHE_TTL_MINUTES));

            $this->pushPreview($phone, $draft, $this->formatEnrichPrompt($draft));
            return '';
        } catch (\Exception $e) {
            Log::error('AdBuilderAgent::handleImage failed', ['error' => $e->getMessage()]);
            return '❌ Terjadi kesalahan. Silakan coba kirim foto lagi atau ketik *batal*.';
        }
    }

    /**
     * Handle text message while user is in 'waiting_input' step.
     */
    public function handleTextWhileWaiting(string $phone, string $text): string
    {
        if ($this->isCancelCommand($text)) {
            Cache::forget(self::CACHE_PREFIX . $phone);
            return "❌ *Pembuatan iklan dibatalkan.*\n\nKetik *buat iklan* kapan saja untuk memulai lagi.";
        }

        return "📸 *Masih menunggu foto produk!*\n\n"
            . "Silakan kirim foto produk yang ingin kamu iklankan.\n\n"
            . "_Sertakan detail di caption: nama, harga, kondisi, lokasi (opsional)_\n\n"
            . "Ketik *batal* untuk membatalkan.";
    }

    /**
     * Handle text when user is in 'enriching' step (AI already has draft, waiting for extra info).
     * User can add price, description, or say "lanjut" to proceed to review.
     */
    public function handleEnriching(string $phone, string $text, array $state): string
    {
        $draft = $state['draft'] ?? [];

        if ($this->isCancelCommand($text)) {
            Cache::forget(self::CACHE_PREFIX . $phone);
            return "❌ *Pembuatan iklan dibatalkan.*\n\nKetik *buat iklan* kapan saja untuk memulai lagi.";
        }

        // Detect "on behalf pasmal" at any enriching step
        if (self::isOnBehalfPasmal($text)) {
            $state['on_behalf_pasmal'] = true;
            Cache::put(self::CACHE_PREFIX . $phone, $state, now()->addMinutes(self::CACHE_TTL_MINUTES));
            return "✅ _Mode atas nama *Pasmal* aktif._\n\nLanjutkan dengan info produk, atau ketik *lanjut* untuk review.";
        }

        // Meta-request: user is asking to see / include the image in the preview — re-send preview with image.
        if ($this->isShowImageRequest($text)) {
            $this->pushPreview($phone, $draft, $this->formatEnrichPrompt($draft));
            return '';
        }

        // "lanjut", "skip", "tidak ada", "sudah ok" → proceed to review as-is
        if (preg_match('/^\s*(lanjut|next|skip|oke|ok|sudah|sudah\s+ok|tidak\s+ada|ga\s+ada|gak\s+ada|langsung\s+post)\s*$/iu', $text)) {
            $newState = ['step' => 'reviewing', 'draft' => $draft];
            if (!empty($state['on_behalf_pasmal'])) $newState['on_behalf_pasmal'] = true;
            Cache::put(self::CACHE_PREFIX . $phone, $newState, now()->addMinutes(self::CACHE_TTL_MINUTES));
            $this->pushPreview($phone, $draft, $this->formatDraftPreview($draft, !empty($state['on_behalf_pasmal'])));
            return '';
        }

        // User provided extra info — ask Gemini to merge it into the draft
        $this->whacenter->sendMessage($phone, '⏳ _Memperbarui draft iklan..._');

        $mergePrompt = 'Kamu copywriter Marketplace Jamaah (komunitas jual-beli Muslim Indonesia). '
            . 'Perbarui draft iklan dengan info tambahan dari penjual, TETAP pertahankan struktur AIDA, '
            . 'bahasa santai-profesional, tanpa clickbait, dan tanpa klaim berlebihan. '
            . "\n\n"
            . 'Info tambahan dari penjual: "' . $text . '"' . "\n"
            . 'Draft saat ini: ' . json_encode($draft, JSON_UNESCAPED_UNICODE) . "\n\n"
            . 'Aturan update:' . "\n"
            . '- Jika info tambahan menyebut harga (Rp, ribu, juta, "150k", dll) → update price (angka bulat) dan price_label (label asli).' . "\n"
            . '- Jika menyebut kondisi → update condition (baru/bekas).' . "\n"
            . '- Jika menyebut lokasi/kota → update location.' . "\n"
            . '- Jika ada deskripsi/spek baru → integrasikan ke description (jangan tempel mentah; rephrase agar mengalir natural & tetap AIDA).' . "\n"
            . '- Title boleh diperbaiki agar lebih punchy bila info baru memunculkan keunggulan (max 60 karakter).' . "\n"
            . '- Notes: kosongkan jika info sudah cukup; isi dengan saran ringkas jika masih ada gap penting.' . "\n\n"
            . 'Jawab HANYA dengan JSON valid struktur sama dengan draft.';

        $result = $this->gemini->generateJson($mergePrompt);

        if ($result && !empty($result['title'])) {
            $result['media_url'] = $draft['media_url'] ?? null;
            $draft = $result;
        } else { // phpcs:ignore
            // Fallback: manual parse common patterns
            $lower = mb_strtolower($text);
            if (preg_match('/(?:harga|price|rp\.?)\s*[\:.]?\s*([\d\.,]+[kKmM]?)/iu', $text, $pm)) {
                $raw = preg_replace('/[^\d]/', '', $pm[1]);
                if (str_ends_with(strtolower($pm[1]), 'k')) $raw = ((int) $raw) * 1000;
                if (str_ends_with(strtolower($pm[1]), 'm')) $raw = ((int) $raw) * 1000000;
                $draft['price'] = (int) $raw;
                $draft['price_label'] = 'Rp ' . number_format((int) $raw, 0, ',', '.');
            }
            if (preg_match('/\b(baru|new)\b/iu', $lower)) $draft['condition'] = 'baru';
            if (preg_match('/\b(bekas|second|seken|used)\b/iu', $lower)) $draft['condition'] = 'bekas';
            if (preg_match('/(?:lokasi|di|area)\s*[\:.]?\s*(.+)/iu', $text, $lm)) {
                $draft['location'] = trim($lm[1]);
            }
        }

        $newState = ['step' => 'reviewing', 'draft' => $draft];
        if (!empty($state['on_behalf_pasmal'])) $newState['on_behalf_pasmal'] = true;
        Cache::put(self::CACHE_PREFIX . $phone, $newState, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $this->pushPreview($phone, $draft, $this->formatDraftPreview($draft, !empty($state['on_behalf_pasmal'])));
        return '';
    }

    /**
     * Handle text message while user is in 'reviewing' step.
     */
    public function handleReview(Message $message, array $state): string
    {
        $phone = $message->sender_number;
        $text = trim($message->raw_body ?? '');
        // Strip square brackets so users who copy literal "[teks]" placeholders still work
        // e.g. "edit [harga 75000]" → "edit harga 75000"
        $text = preg_replace('/[\[\]]/', '', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $draft = $state['draft'] ?? [];
        $onBehalfPasmal = !empty($state['on_behalf_pasmal']);

        if ($this->isCancelCommand($text)) {
            Cache::forget(self::CACHE_PREFIX . $phone);
            return "❌ *Pembuatan iklan dibatalkan.*\n\nKetik *buat iklan* kapan saja untuk memulai lagi.";
        }

        // Meta-request: user asks to display image in preview → re-send preview with photo.
        if ($this->isShowImageRequest($text)) {
            $this->pushPreview($phone, $draft, $this->formatDraftPreview($draft, $onBehalfPasmal));
            return '';
        }

        // Detect "on behalf pasmal" at review step
        if (self::isOnBehalfPasmal($text)) {
            $state['on_behalf_pasmal'] = true;
            $onBehalfPasmal = true;
            Cache::put(self::CACHE_PREFIX . $phone, $state, now()->addMinutes(self::CACHE_TTL_MINUTES));
            return "✅ _Mode atas nama *Pasmal* aktif._\n\nKetik *ya* untuk posting.";
        }

        // Confirm & post
        if (preg_match('/^\s*(ya|yes|ok|oke|iya|post|posting|setuju|lanjut|kirim|(posting|post|kirim)\s+ke\s+grup)\s*$/iu', $text)) {
            return $this->confirmAndPost($message, $draft, $onBehalfPasmal);
        }

        // Edit field: "edit judul Gamis Syari Premium"
        if (preg_match('/^\s*edit\s+(\S+)\s+(.*)/isu', $text, $m)) {
            $field = mb_strtolower(trim($m[1]));
            $value = trim($m[2]);

            $fieldMap = [
                'judul' => 'title', 'title' => 'title', 'nama' => 'title',
                'harga' => '_price', 'price' => '_price',
                'tipe' => 'price_type', 'tipe_harga' => 'price_type',
                'deskripsi' => 'description', 'desc' => 'description', 'keterangan' => 'description',
                'kategori' => 'category', 'cat' => 'category',
                'kondisi' => 'condition', 'condition' => 'condition',
                'lokasi' => 'location', 'location' => 'location', 'alamat' => 'location',
            ];

            $key = $fieldMap[$field] ?? null;
            if ($key) {
                if ($key === '_price') {
                    // Parse price with optional type prefix: "nego 500000" / "fix 500000" / "lelang 1jt"
                    $lv = mb_strtolower($value);
                    if (preg_match('/\b(nego)\b/iu', $lv)) $draft['price_type'] = 'nego';
                    elseif (preg_match('/\b(lelang|auction)\b/iu', $lv)) $draft['price_type'] = 'lelang';
                    elseif (preg_match('/\b(fix|tetap)\b/iu', $lv)) $draft['price_type'] = 'fix';
                    $clean = preg_replace('/\b(nego|fix|tetap|lelang|auction)\b\s*/iu', '', $value);
                    if (preg_match('/(\d[\d.,]*)\s*(jt|juta)/iu', $clean, $jm)) {
                        $draft['price'] = (int) round((float) str_replace(['.', ','], ['', '.'], $jm[1]) * 1000000);
                    } elseif (preg_match('/(\d[\d.,]*)\s*[kK]\b/', $clean, $km)) {
                        $draft['price'] = (int) round((float) str_replace(['.', ','], ['', '.'], $km[1]) * 1000);
                    } else {
                        $numStr = preg_replace('/[^\d]/', '', $clean);
                        $draft['price'] = $numStr ? (int) $numStr : 0;
                    }
                    $draft['price_label'] = null;
                } else {
                    $draft[$key] = $value;
                }
                $updState = ['step' => 'reviewing', 'draft' => $draft];
                if ($onBehalfPasmal) $updState['on_behalf_pasmal'] = true;
                Cache::put(self::CACHE_PREFIX . $phone, $updState, now()->addMinutes(self::CACHE_TTL_MINUTES));
                $this->pushPreview(
                    $phone,
                    $draft,
                    "✅ *" . ucfirst($field) . "* diperbarui!\n\n" . $this->formatDraftPreview($draft, $onBehalfPasmal)
                );
                return '';
            }

            // Field tidak dikenali — fall through to AI enrichment so user's intent
            // ("edit info pengiriman ojol", "edit ada garansi 1 minggu") tetap dipakai.
        }

        // Anything else: treat as enrichment info & let AI merge into the draft.
        // Covers natural inputs like "tambah info ojol same day", "ada garansi 1 bulan",
        // "edit free ongkir Jabodetabek", etc.
        return $this->enrichDraftWithText($message, $state, $text);
    }

    /**
     * Use Gemini to merge free-form user text into the existing draft, then re-show preview.
     * Used when user input in 'reviewing' step doesn't match strict ya/edit/cancel commands.
     */
    private function enrichDraftWithText(Message $message, array $state, string $text): string
    {
        $phone = $message->sender_number;
        $draft = $state['draft'] ?? [];
        $onBehalfPasmal = !empty($state['on_behalf_pasmal']);

        $this->whacenter->sendMessage($phone, '⏳ _Memperbarui iklan dengan info baru..._');

        $mergePrompt = 'Kamu copywriter Marketplace Jamaah (komunitas jual-beli Muslim Indonesia). '
            . 'Tambahkan / perbarui draft iklan di bawah ini dengan info baru dari penjual. '
            . 'Pertahankan struktur AIDA, bahasa santai-profesional, tanpa clickbait, tanpa klaim berlebihan. '
            . "\n\n"
            . 'Info baru dari penjual: "' . $text . '"' . "\n"
            . 'Draft saat ini: ' . json_encode($draft, JSON_UNESCAPED_UNICODE) . "\n\n"
            . 'Aturan update:' . "\n"
            . '- Jika info menyebut harga (Rp, ribu, juta, "150k") → update price (angka bulat) dan price_label.' . "\n"
            . '- Jika menyebut kondisi → update condition (baru/bekas).' . "\n"
            . '- Jika menyebut lokasi/kota → update location.' . "\n"
            . '- Jika menyebut info pengiriman, garansi, kelengkapan, syarat order → integrasikan ke description (rephrase agar mengalir, jangan tempel mentah).' . "\n"
            . '- Title boleh diperbaiki agar lebih punchy (max 60 karakter) jika info baru memunculkan keunggulan.' . "\n"
            . '- Notes: kosongkan jika info sudah cukup; isi saran ringkas jika masih ada gap.' . "\n\n"
            . 'Jawab HANYA dengan JSON valid struktur sama dengan draft.';

        $result = $this->gemini->generateJson($mergePrompt);

        if ($result && !empty($result['title'])) {
            $result['media_url'] = $draft['media_url'] ?? null;
            $draft = $result;
        }

        $newState = ['step' => 'reviewing', 'draft' => $draft];
        if ($onBehalfPasmal) $newState['on_behalf_pasmal'] = true;
        Cache::put(self::CACHE_PREFIX . $phone, $newState, now()->addMinutes(self::CACHE_TTL_MINUTES));

        $this->pushPreview(
            $phone,
            $draft,
            "✅ *Draft diperbarui dengan info barumu!*\n\n" . $this->formatDraftPreview($draft, $onBehalfPasmal)
        );
        return '';
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function formatEnrichPrompt(array $draft): string
    {
        $priceStr = $draft['price_label'] ?? '';
        if (!$priceStr && !empty($draft['price']) && $draft['price'] > 0) {
            $priceStr = 'Rp ' . number_format((float) $draft['price'], 0, ',', '.');
        }

        $lines = ["📸 *Foto diterima! AI sudah buat draft awal:*\n"];
        $lines[] = "📦 *{$draft['title']}*";
        $lines[] = "🏷️ Kategori: " . ($draft['category'] ?? '-');
        $lines[] = "💰 Harga: " . ($priceStr ?: '_belum terdeteksi_');
        $lines[] = "🔖 Kondisi: " . ($draft['condition'] ?? '-');

        if (!empty($draft['notes'])) {
            $lines[] = "\n💬 _AI: {$draft['notes']}_";
        }

        $lines[] = "\n───────────────";
        $lines[] = "Ada info tambahan yang ingin ditambahkan?";
        $lines[] = "_(harga, kondisi, lokasi, deskripsi, dll)_\n";
        $lines[] = "Atau ketik:\n• *lanjut* → lihat draft lengkap & posting\n• *batal* → batalkan";

        return implode("\n", $lines);
    }

    private function isCancelCommand(string $text): bool
    {
        return (bool) preg_match('/^\s*(batal|cancel|hapus|stop|keluar)\s*$/iu', $text);
    }

    /**
     * Detect meta-requests asking the bot to display/include the uploaded image
     * in the preview (as opposed to product info to merge into the draft).
     */
    private function isShowImageRequest(string $text): bool
    {
        return (bool) preg_match(
            '/\b(tampil\w*|tampilin|munculk\w*|muncul\w*|perlihatk\w*|lihatk\w*|kirim|tunjukk\w*|sertak\w*|lampirk\w*|include|show)\b[^\n]{0,40}\b(gambar|foto|image|picture|pic)\b/iu',
            $text
        ) || (bool) preg_match(
            '/\b(gambar|foto|image|picture|pic)\w*\b[^\n]{0,40}\b(jangan\s+lupa|tampil\w*|muncul\w*|sertak\w*|lampirk\w*|tunjukk\w*|lihatk\w*|show)\b/iu',
            $text
        );
    }

    /**
     * Send a preview message. If the draft has an image, send it as image+caption
     * so the uploaded photo is visible inline; otherwise fall back to text.
     */
    private function pushPreview(string $phone, array $draft, string $caption): void
    {
        $mediaUrl = $draft['media_url'] ?? null;
        try {
            if ($mediaUrl) {
                $this->whacenter->sendImageMessage($phone, $caption, $mediaUrl);
                return;
            }
        } catch (\Exception $e) {
            Log::warning('AdBuilderAgent::pushPreview image send failed, falling back to text', [
                'error' => $e->getMessage(),
            ]);
        }
        $this->whacenter->sendMessage($phone, $caption);
    }

    private function formatDraftPreview(array $draft, bool $onBehalfPasmal = false): string
    {
        $priceType = $draft['price_type'] ?? 'fix';
        $priceStr = $draft['price_label'] ?? '';
        if (!$priceStr && !empty($draft['price']) && $draft['price'] > 0) {
            $rp = 'Rp ' . number_format((float) $draft['price'], 0, ',', '.');
            $priceStr = match ($priceType) {
                'nego'   => "{$rp} (Nego)",
                'lelang' => "Lelang mulai {$rp}",
                default  => $rp,
            };
        }
        if (!$priceStr) {
            $priceStr = match ($priceType) {
                'nego'   => 'Harga Nego',
                'lelang' => 'Harga Lelang',
                default  => 'Belum dicantumkan',
            };
        }

        $priceTypeLabel = match ($priceType) {
            'nego'   => '🤝 Nego',
            'lelang' => '🔨 Lelang',
            default  => '🏷️ Fix',
        };

        $condition = match (mb_strtolower($draft['condition'] ?? '')) {
            'baru', 'new' => '✨ Baru',
            'bekas', 'second', 'used', 'seken' => '🔄 Bekas',
            default => $draft['condition'] ? ucfirst($draft['condition']) : '-',
        };

        $locLine = !empty($draft['location']) ? "\n📍 *Lokasi:* {$draft['location']}" : '';
        $notesLine = !empty($draft['notes']) ? "\n\n💬 _Catatan AI: {$draft['notes']}_" : '';
        $hasImage = '';
        $onBehalfLine = $onBehalfPasmal ? "\n📋 *Atas nama:* Pasmal" : '';

        return "✨ *Preview Draft Iklan:*\n\n"
            . "📦 *{$draft['title']}*\n\n"
            . "📝 {$draft['description']}\n\n"
            . "💰 *Harga:* {$priceStr}\n"
            . "🏷️ *Tipe harga:* {$priceTypeLabel}\n"
            . "📂 *Kategori:* " . ($draft['category'] ?? '-') . "\n"
            . "🔖 *Kondisi:* {$condition}"
            . $locLine
            . $onBehalfLine
            . $hasImage
            . $notesLine
            . "\n\n───────────────\n"
            . "Ketik *ya* → *posting ke grup*\n"
            . "Ketik *edit [field] [nilai]* → ubah detail\n"
            . "Ketik *batal* → batalkan\n\n"
            . "_Contoh edit: `edit harga 75000` atau `edit lokasi Bekasi`_";
    }

    private function confirmAndPost(Message $message, array $draft, bool $onBehalfPasmal = false): string
    {
        $phone = $message->sender_number;

        // On behalf Pasmal: use Pasmal's contact info instead of poster's
        if ($onBehalfPasmal) {
            $pasmalPhone = Setting::get('pasmal_contact_phone', '');
            $pasmalName  = Setting::get('pasmal_contact_name', 'Pasaramal Jamaah');
            $contact     = $pasmalPhone ? Contact::where('phone_number', $pasmalPhone)->first() : null;
            $name        = $pasmalName;
            $contactPhone = $pasmalPhone ?: $phone;
        } else {
            $contact     = Contact::where('phone_number', $phone)->first();
            $name        = $contact?->name ?? $phone;
            $contactPhone = $phone;
        }

        // Resolve category
        $categoryName = $draft['category'] ?? '';
        $category = Category::whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($categoryName) . '%'])
            ->first()
            ?? Category::where('slug', 'lainnya')->first();

        // Find active WAG (same resolution as group-origin ads)
        $group = WhatsappGroup::where('is_active', true)->first();

        // Build price numeric value
        $priceNumeric = null;
        if (!empty($draft['price']) && $draft['price'] > 0) {
            $priceNumeric = (float) $draft['price'];
        } elseif (!empty($draft['price_label'])) {
            $numStr = preg_replace('/[^\d]/', '', $draft['price_label']);
            $priceNumeric = $numStr ? (float) $numStr : null;
        }

        // Normalize condition to DB-allowed enum (listings_condition_check: new/used/unknown).
        // Gemini returns Indonesian "baru"/"bekas" — map before insert.
        $condition = match (mb_strtolower(trim((string) ($draft['condition'] ?? '')))) {
            'baru', 'new'                             => 'new',
            'bekas', 'second', 'seken', 'used'        => 'used',
            default                                   => 'unknown',
        };

        // Create listing record — attach $message so BroadcastAgent can resolve group via listing->message->group fallback
        $listing = Listing::create([
            'message_id' => $message->id,
            'whatsapp_group_id' => $group?->id,
            'category_id' => $category?->id,
            'contact_id' => $contact?->id,
            'title' => $draft['title'],
            'description' => $draft['description'],
            'price' => $priceNumeric,
            'price_label' => $draft['price_label'] ?? null,
            'price_type' => $draft['price_type'] ?? 'fix',
            'contact_number' => $contactPhone,
            'contact_name' => $name,
            'media_urls' => !empty($draft['media_url']) ? [$draft['media_url']] : null,
            'location' => $draft['location'] ?? null,
            'condition' => $condition,
            'status' => 'active',
            'source_date' => now(),
        ]);

        // Clear state BEFORE delegating — success path below uses $listing directly.
        Cache::forget(self::CACHE_PREFIX . $phone);

        // Delegate WAG posting to BroadcastAgent — same path used by group-origin listings.
        $posted = false;
        try {
            app(BroadcastAgent::class)->handle($message, $listing);
            $posted = true;
            if ($group) {
                $group->increment('ad_count');
            }
        } catch (\Exception $e) {
            Log::error('AdBuilderAgent: BroadcastAgent failed to post listing', [
                'error' => $e->getMessage(),
                'listing_id' => $listing->id,
            ]);
        }

        $listingUrl = rtrim(config('app.url'), '/') . '/p/' . $listing->id;

        if ($posted) {
            return "✅ *Iklan berhasil diposting ke grup!*\n\n"
                . "📦 *{$draft['title']}*\n\n"
                . "🔗 Lihat iklan: {$listingUrl}\n\n"
                . "_Edit kapan saja: ketik *edit #{$listing->id}*_";
        }

        return "⚠️ Iklan tersimpan tapi gagal diposting ke grup otomatis.\n\n"
            . "🔗 Lihat iklan: {$listingUrl}\n\n"
            . "_Silakan posting manual atau hubungi admin._";
    }
}
