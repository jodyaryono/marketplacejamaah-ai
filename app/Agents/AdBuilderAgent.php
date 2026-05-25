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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdBuilderAgent
{
    private const CACHE_TTL_MINUTES = 30;
    private const CACHE_PREFIX = 'ad_builder:';
    private const ACTIVE_INDEX_KEY = 'ad_builder:active_phones';
    private const LOCK_PREFIX = 'ad_builder_lock:';
    private const BATCH_DIR = 'adbuilder';

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
     * Centralized state writer — stamps last_activity_at and registers the phone
     * in the active-sessions index so the stale-session checker can find it.
     */
    private function putState(string $phone, array $state): void
    {
        $state['last_activity_at'] = now()->toIso8601String();
        Cache::put(self::CACHE_PREFIX . $phone, $state, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $this->registerActivePhone($phone);
    }

    private function registerActivePhone(string $phone): void
    {
        $active = Cache::get(self::ACTIVE_INDEX_KEY, []);
        if (!in_array($phone, $active, true)) {
            $active[] = $phone;
            Cache::put(self::ACTIVE_INDEX_KEY, $active, now()->addDay());
        }
    }

    private function unregisterActivePhone(string $phone): void
    {
        $active = Cache::get(self::ACTIVE_INDEX_KEY, []);
        $active = array_values(array_filter($active, fn($p) => $p !== $phone));
        Cache::put(self::ACTIVE_INDEX_KEY, $active, now()->addDay());
    }

    /**
     * Phones currently in an ad-builder session — used by ads:check-stale.
     */
    public function getActivePhones(): array
    {
        return Cache::get(self::ACTIVE_INDEX_KEY, []);
    }

    /**
     * Cancel & clear an ad-builder session.
     */
    public function cancelSession(string $phone): void
    {
        Cache::forget(self::CACHE_PREFIX . $phone);
        $this->unregisterActivePhone($phone);
        $this->cleanupBatchFiles($phone);
    }

    /**
     * Hapus folder media batch on-disk untuk phone tertentu. Dipanggil setelah
     * batch sukses di-analyze, saat cancel, atau saat session di-clear.
     */
    private function cleanupBatchFiles(string $phone): void
    {
        try {
            $dir = self::BATCH_DIR . '/' . $this->safePhone($phone);
            if (Storage::disk('local')->exists($dir)) {
                Storage::disk('local')->deleteDirectory($dir);
            }
        } catch (\Throwable $e) {
            Log::warning('AdBuilderAgent::cleanupBatchFiles failed', ['phone' => $phone, 'error' => $e->getMessage()]);
        }
    }

    private function safePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone) ?: 'unknown';
    }

    /**
     * Mark that the stale-confirmation prompt has been sent. Used by check-stale.
     * Returns true if the flag was newly set, false if it was already present.
     */
    public function markStalePrompt(string $phone): bool
    {
        $state = $this->getState($phone);
        if (!$state || !empty($state['stale_prompt_sent_at'])) {
            return false;
        }
        $state['stale_prompt_sent_at'] = now()->toIso8601String();
        // Bypass putState() so we DO NOT refresh last_activity_at — the grace
        // window is measured against stale_prompt_sent_at and the original idle
        // window stays intact for the cancel decision.
        Cache::put(self::CACHE_PREFIX . $phone, $state, now()->addMinutes(self::CACHE_TTL_MINUTES));
        return true;
    }

    public function clearStalePrompt(string $phone): void
    {
        $state = $this->getState($phone);
        if ($state && isset($state['stale_prompt_sent_at'])) {
            unset($state['stale_prompt_sent_at']);
            $this->putState($phone, $state);
        }
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
        $this->putState($phone, $state);

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
        $this->putState($phone, ['step' => 'waiting_input']);
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

        // COLLECT-FIRST flow: jangan analyze langsung. Kumpulkan dulu, baru
        // analyze SEMUA sekaligus saat user bilang "cukup" / "analisa".
        $state = Cache::get(self::CACHE_PREFIX . $phone, []);
        $step = $state['step'] ?? 'waiting_input';

        if (in_array($step, ['waiting_input', 'collecting'], true)) {
            // Persist base64 ke disk DULU di luar lock — write file bisa lambat,
            // jangan tahan lock selama itu. Hanya path + url + mime yang masuk cache.
            [$mediaPath, $mediaMime] = $this->persistMediaToDisk($phone, $mediaUrl, $mediaData);
            return $this->addToCollectingBatch($phone, $mediaUrl, $mediaPath, $mediaMime, $caption);
        }

        // step 'enriching' / 'reviewing' → user kirim foto tambahan setelah draft
        // sudah ada. Tetap pakai pola lama (analyze + merge).
        $hasExistingDraft = !empty($state['draft']['title'] ?? null);
        if (!$hasExistingDraft) {
            $this->whacenter->sendMessage($phone, '⏳ _Sedang menganalisa gambar dan menyiapkan draft iklan..._');
        }

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
                . '2. Description — ATURAN UTAMA: WAJIB pertahankan SEMUA isi caption asli dari penjual VERBATIM, jangan kurangi/ringkas/hapus baris apapun. ' . "\n"
                . '   Boleh ditambah, tidak boleh dikurangi. Caption asli harus muncul utuh di description. ' . "\n"
                . '   Boleh tambahkan hook AIDA SEBELUM caption asli (1-2 kalimat pengantar yang menjual: hook manfaat, soft CTA halal). ' . "\n"
                . '   Boleh tambahkan emoji relevan (📏 ukuran, 🎁 hadiah, ✨ baru, 🔄 second) — maksimal 3, jangan spam. ' . "\n"
                . '   Hindari clickbait, klaim berlebihan, atau kata "termurah" tanpa dasar.' . "\n"
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
                // Kalau sudah ada draft sebelumnya (mis. foto kedua dalam batch yang
                // gagal di-analyze), jangan rusak draft yang ada. Cukup info silent.
                $existing = Cache::get(self::CACHE_PREFIX . $phone, []);
                if (!empty($existing['draft']['title'])) {
                    return "⚠️ Foto tambahan gagal dianalisa (draft aman).";
                }
                return '❌ AI gagal membuat draft iklan. Coba kirim foto yang lebih jelas, atau ketik *batal*.';
            }

            $draft['media_url'] = $mediaUrl;

            // Preserve on_behalf_pasmal flag from previous state
            $currentState = Cache::get(self::CACHE_PREFIX . $phone, []);

            // MULTI-IMAGE MERGE: kalau sudah ada draft (user kirim foto kedua/ketiga
            // dalam 1 batch ad — mis. banner + pricing page + spec), gabungkan info
            // baru ke draft lama daripada overwrite.
            $existingDraft = $currentState['draft'] ?? null;
            $isBatchFollowup = $existingDraft && !empty($existingDraft['title']);

            if ($isBatchFollowup) {
                $merged = $existingDraft;
                foreach (['title','description','category','condition','location','notes','price_label','price_type'] as $k) {
                    $newVal = trim((string) ($draft[$k] ?? ''));
                    $oldVal = trim((string) ($merged[$k] ?? ''));
                    if ($newVal !== '' && ($oldVal === '' || $oldVal === 'fix' || $oldVal === '-')) {
                        $merged[$k] = $newVal;
                    }
                }
                if ((empty($merged['price']) || $merged['price'] <= 0) && !empty($draft['price']) && $draft['price'] > 0) {
                    $merged['price'] = $draft['price'];
                }
                $newDesc = trim((string) ($draft['description'] ?? ''));
                $oldDesc = trim((string) ($merged['description'] ?? ''));
                if ($newDesc !== '' && $oldDesc !== '' && stripos($oldDesc, mb_substr($newDesc, 0, 40)) === false) {
                    $merged['description'] = $oldDesc . "\n\n" . $newDesc;
                }
                $mediaUrls = $merged['media_urls'] ?? (isset($merged['media_url']) && $merged['media_url'] ? [$merged['media_url']] : []);
                if ($mediaUrl && !in_array($mediaUrl, $mediaUrls, true)) {
                    $mediaUrls[] = $mediaUrl;
                }
                $merged['media_urls'] = $mediaUrls;
                $merged['media_url'] = $mediaUrls[0] ?? $mediaUrl;
                $draft = $merged;
            } else {
                // First image — init media_urls dari single media
                $draft['media_urls'] = $mediaUrl ? [$mediaUrl] : [];
            }

            $newState = ['step' => 'enriching', 'draft' => $draft];
            if (!empty($currentState['on_behalf_pasmal'])) {
                $newState['on_behalf_pasmal'] = true;
            }
            if (!empty($caption) && self::isOnBehalfPasmal($caption)) {
                $newState['on_behalf_pasmal'] = true;
            }

            $this->putState($phone, $newState);

            // Foto pertama → kirim preview penuh.
            // Foto kedua+ → ack 1-baris saja, no sugesti spam.
            if ($isBatchFollowup) {
                $count = count($draft['media_urls'] ?? []);
                $priceInfo = !empty($draft['price']) && $draft['price'] > 0
                    ? " — harga: Rp " . number_format($draft['price'], 0, ',', '.')
                    : '';
                return "✅ Foto {$count} digabung ke draft{$priceInfo}.";
            }

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
            $this->cancelSession($phone);
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
    /**
     * Push image ke batch koleksi (collecting step). Tidak analyze dulu.
     * User kirim semua foto/video + deskripsi dulu, baru ketik "cukup" untuk
     * trigger analyze SEMUA sekaligus.
     */
    /**
     * Tulis media (base64 atau download URL) ke storage/app/adbuilder/{phone}/{uuid}.
     * Return [relativePath|null, mime|null]. Tidak menahan lock — file I/O di luar
     * critical section. Kalau gagal, batch entry tetap dibuat dengan path=null dan
     * fallback ke URL saat analyze (URL WhaCenter bisa expired tapi worth a try).
     */
    private function persistMediaToDisk(string $phone, ?string $url, ?array $data): array
    {
        try {
            $base64 = null;
            $mime = null;
            if ($data && !empty($data['data'])) {
                $base64 = $data['data'];
                $mime = $data['mimetype'] ?? 'image/jpeg';
            } elseif ($url) {
                $resp = Http::timeout(15)->get($url);
                if ($resp->failed()) {
                    return [null, null];
                }
                $base64 = base64_encode($resp->body());
                $mime = $resp->header('Content-Type') ?: 'image/jpeg';
            } else {
                return [null, null];
            }

            $ext = match (true) {
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'webp') => 'webp',
                str_contains($mime, 'gif') => 'gif',
                str_contains($mime, 'mp4') => 'mp4',
                str_contains($mime, 'video') => 'mp4',
                default => 'jpg',
            };
            $rel = self::BATCH_DIR . '/' . $this->safePhone($phone) . '/' . Str::uuid()->toString() . '.' . $ext;
            Storage::disk('local')->put($rel, base64_decode($base64));
            return [$rel, $mime];
        } catch (\Throwable $e) {
            Log::warning('AdBuilderAgent::persistMediaToDisk failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return [null, null];
        }
    }

    private function addToCollectingBatch(string $phone, ?string $url, ?string $path, ?string $mime, string $caption): string
    {
        // Filter caption: buang kalimat instruksi meta (mis. 'saya mau buat iklan')
        // dan URL telanjang supaya tidak nempel sebagai 'caption produk'.
        $cleanCaption = $this->stripMetaInstructions($caption);

        // ATOMIC read-modify-write — multiple WA image webhooks datang paralel
        // saat user kirim foto burst. Tanpa lock, batch corrupt (counter 3→2→4).
        $lock = Cache::lock(self::LOCK_PREFIX . $phone, 10);
        $count = 0;
        try {
            $lock->block(5);
            $state = Cache::get(self::CACHE_PREFIX . $phone, []);
            $batch = $state['batch'] ?? [];
            $batch[] = [
                'url' => $url,
                'path' => $path,
                'mime' => $mime,
                'caption' => $cleanCaption,
            ];
            $state['step'] = 'collecting';
            $state['batch'] = $batch;
            if ($cleanCaption !== '' && empty($state['initial_caption'])) {
                $state['initial_caption'] = $cleanCaption;
            }
            $this->putState($phone, $state);
            $count = count($batch);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            Log::warning('AdBuilderAgent::addToCollectingBatch lock timeout', ['phone' => $phone]);
            return "📸 Foto diterima (sedang diproses). Kirim *cukup* kalau sudah selesai upload.";
        } finally {
            optional($lock)->release();
        }

        // Foto PERTAMA: ack lengkap dengan instruksi + catatan bahwa foto ini
        // jadi cover iklan. Foto kedua+: SILENT (return '') — sebelumnya tiap
        // foto kirim "X foto terkumpul" yang bikin chat ramai & user bingung.
        if ($count === 1) {
            $captionLine = $cleanCaption !== ''
                ? "✏️ _Caption tercatat: \"" . mb_strimwidth($cleanCaption, 0, 80, '...') . "\"_\n"
                : '';
            return "📸 *Foto diterima — ini akan jadi cover iklan.*\n"
                . $captionLine
                . "\n"
                . "Boleh kirim *foto/video lain* atau *info tambahan* (harga, lokasi, kontak, spek).\n\n"
                . "Ketik *cukup* kalau sudah selesai upload, *batal* untuk membatalkan.";
        }
        return '';
    }

    /**
     * Buang kalimat instruksi meta dari caption supaya tidak salah dianggap
     * info produk. Contoh yang dibuang:
     *   "saya mau buat iklan https://..."  → ""
     *   "tolong buatin iklan dari foto ini" → ""
     *   "buatkan redaksional menarik"      → ""
     *   "bikin iklan ya"                   → ""
     * URL valid berdiri sendiri TANPA kalimat meta tetap dipertahankan sebagai
     * info link produk.
     */
    private function stripMetaInstructions(string $caption): string
    {
        $c = trim($caption);
        if ($c === '') return '';

        // Patterns kalimat meta-instruction
        $patterns = [
            '/\bsaya\s+mau\s+(buat|bikin|pasang|posting|post|tambah)\s+iklan\b[^\n]*/iu',
            '/\b(tolong|mohon|please|plz)\s+(buat|bikin|pasang|buatin|bikinin)\s+(iklan|redaksional|copy|deskripsi)\b[^\n]*/iu',
            '/\b(buat|bikin|buatin|bikinin)\s+(iklan|redaksional|copy|deskripsi)\s+(dari|untuk|nya)?\b[^\n]*/iu',
            '/\bsaya\s+mau\s+jualan\s+via\s+bot\b[^\n]*/iu',
            '/\bcoba\s+(buat|bikin)\s+iklan\b[^\n]*/iu',
        ];
        foreach ($patterns as $p) {
            $c = preg_replace($p, '', $c);
        }
        $c = trim(preg_replace('/\s+/u', ' ', $c));
        return $c;
    }

    /**
     * Handle text message saat step='collecting'.
     * - cancel → batalkan
     * - cukup/analisa/dst → trigger batch analyze
     * - lainnya → append sebagai deskripsi tambahan
     */
    public function handleCollecting(string $phone, string $text, array $state): string
    {
        if ($this->isCancelCommand($text)) {
            $this->cancelSession($phone);
            return "❌ *Pembuatan iklan dibatalkan.*\n\nKetik *buat iklan* kapan saja untuk memulai lagi.";
        }

        if (preg_match('/^\s*(cukup|sudah|sudahin|analisa|analyze|proses|lanjut|next|ok|oke|sip|tidak\s*ada\s*lagi|tidak\s*ada|ga\s*ada|gak\s*ada|nggak\s*ada|tidak|gak|nggak|done|finish|selesai)\s*$/iu', $text)) {
            // Re-read state freshly DI DALAM lock supaya tidak race dengan
            // image webhook yang masih push batch (foto bisa masuk milidetik lalu).
            $lock = Cache::lock(self::LOCK_PREFIX . $phone, 15);
            $freshState = $state;
            try {
                $lock->block(5);
                $freshState = Cache::get(self::CACHE_PREFIX . $phone, $state);
            } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
                Log::warning('AdBuilderAgent::handleCollecting cukup lock timeout', ['phone' => $phone]);
            } finally {
                optional($lock)->release();
            }

            $batchCount = count($freshState['batch'] ?? []);
            if ($batchCount === 0) {
                return "⚠️ Belum ada foto. Kirim foto produk dulu ya.";
            }

            // Tandai step=analyzing supaya handler text/image berikutnya tidak
            // double-trigger analyze. Lock biar atomic dengan webhook lain.
            $alreadyAnalyzing = false;
            $lock = Cache::lock(self::LOCK_PREFIX . $phone, 10);
            try {
                $lock->block(5);
                $s = Cache::get(self::CACHE_PREFIX . $phone, $freshState);
                if (($s['step'] ?? '') === 'analyzing') {
                    $alreadyAnalyzing = true;
                } else {
                    $s['step'] = 'analyzing';
                    $this->putState($phone, $s);
                }
            } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
                Log::warning('AdBuilderAgent::handleCollecting analyzing-flag lock timeout', ['phone' => $phone]);
            } finally {
                optional($lock)->release();
            }

            if ($alreadyAnalyzing) {
                return "⏳ AI masih memproses foto-foto sebelumnya. Sebentar ya, drafnya menyusul...";
            }

            // Dispatch async job — analyze + preview dikerjakan di queue worker.
            // Bot balas user INSTAN dengan ack di bawah, draft preview menyusul
            // sebagai chat terpisah ketika job selesai.
            \App\Jobs\ProcessAdBuilderBatchJob::dispatch($phone);

            return "⚡ *Sip, semua foto diterima!*\n\nAI sedang menyiapkan draft iklan. Bentar ya, drafnya akan menyusul di chat berikutnya...";
        }

        // Append sebagai deskripsi tambahan (multiple entries digabung)
        $extra = trim(($state['extra_caption'] ?? '') . "\n" . $text);
        $state['extra_caption'] = $extra;
        $this->putState($phone, $state);

        $batchCount = count($state['batch'] ?? []);
        return "✏️ Catatan tambahan disimpan ({$batchCount} foto/video terkumpul).\n\nKirim foto/video lagi, tambah deskripsi, atau ketik *cukup* untuk mulai AI analisa.";
    }

    /**
     * Eksekusi batch analyze SECARA SYNC (dipanggil oleh queue job).
     * Memproses semua foto + caption → draft → preview. Tidak kirim ack di sini
     * (ack instan sudah dikirim caller di main webhook thread sebelum dispatch).
     */
    public function executeBatchAnalysis(string $phone): void
    {
        $state = Cache::get(self::CACHE_PREFIX . $phone, []);
        $batch = $state['batch'] ?? [];
        if (empty($batch)) {
            $this->whacenter->sendMessage($phone, "⚠️ Belum ada foto. Kirim foto produk dulu ya.");
            return;
        }

        $combinedCaption = trim(
            ($state['initial_caption'] ?? '')
            . (!empty($state['extra_caption']) ? "\n\nInfo tambahan dari penjual: " . trim($state['extra_caption']) : '')
        );

        // Cover = foto pertama yang user upload (urutan batch).
        $mediaUrls = [];
        foreach ($batch as $item) {
            if (!empty($item['url'])) {
                $mediaUrls[] = $item['url'];
            }
        }

        // SINGLE multimodal call: kirim SEMUA foto + caption gabungan ke Gemini
        // dalam 1 request. Sebelumnya tiap foto = 1 call → cepat kena rate-limit
        // 429 (Gemini free tier ~15 RPM, vision lebih berat). Hasil draft juga
        // lebih koheren karena AI lihat semua foto sekaligus.
        $images = $this->loadBatchImagesAsBase64($batch);
        if (empty($images)) {
            Log::warning('AdBuilderAgent::executeBatchAnalysis no readable images', [
                'phone' => $phone,
                'batch_count' => count($batch),
            ]);
            $this->whacenter->sendMessage($phone, "❌ Foto-foto tidak bisa dibaca. Ketik *batal* lalu mulai lagi dengan foto yang lebih jelas.");
            return;
        }

        $merged = $this->analyzeBatchImages($images, $combinedCaption);
        if (!$merged || empty($merged['title'])) {
            Log::warning('AdBuilderAgent::executeBatchAnalysis gemini multi-image failed', [
                'phone' => $phone,
                'batch_count' => count($batch),
                'image_count' => count($images),
            ]);
            $this->whacenter->sendMessage($phone, "❌ AI gagal menganalisa foto-foto Kakak. Ketik *batal* lalu mulai lagi dengan foto yang lebih jelas.");
            return;
        }
        $failures = count($batch) - count($images);

        $merged['media_urls'] = array_values(array_unique($mediaUrls));
        $merged['media_url'] = $merged['media_urls'][0] ?? null;

        $newState = ['step' => 'enriching', 'draft' => $merged];
        if (!empty($state['on_behalf_pasmal'])) {
            $newState['on_behalf_pasmal'] = true;
        }
        if (!empty($state['initial_caption']) && self::isOnBehalfPasmal($state['initial_caption'])) {
            $newState['on_behalf_pasmal'] = true;
        }
        $this->putState($phone, $newState);

        // Cleanup file media di disk — sudah tidak diperlukan setelah merged.
        $this->cleanupBatchFiles($phone);

        if ($failures > 0) {
            $this->whacenter->sendMessage($phone, "ℹ️ {$failures} foto gagal dianalisa, draft tetap dibuat dari sisanya.");
        }

        $this->pushPreview($phone, $merged, $this->formatEnrichPrompt($merged));
    }

    /**
     * Load semua foto di batch jadi array of ['mime_type','data' (base64)].
     * Prefer file di disk; fallback download URL kalau path hilang. Item yang
     * gagal di-load di-skip (failures di-count di caller).
     */
    private function loadBatchImagesAsBase64(array $batch): array
    {
        $out = [];
        foreach ($batch as $item) {
            $path = $item['path'] ?? null;
            $url  = $item['url'] ?? null;
            $mime = $item['mime'] ?? 'image/jpeg';
            try {
                if ($path && Storage::disk('local')->exists($path)) {
                    $out[] = ['mime_type' => $mime, 'data' => base64_encode(Storage::disk('local')->get($path))];
                    continue;
                }
                if ($url) {
                    $resp = Http::timeout(15)->get($url);
                    if ($resp->failed()) {
                        Log::warning('AdBuilderAgent::loadBatchImagesAsBase64 url failed', ['url' => $url, 'status' => $resp->status()]);
                        continue;
                    }
                    $out[] = [
                        'mime_type' => $resp->header('Content-Type') ?: $mime,
                        'data' => base64_encode($resp->body()),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('AdBuilderAgent::loadBatchImagesAsBase64 exception', ['error' => $e->getMessage()]);
            }
        }
        return $out;
    }

    /**
     * Single Gemini multimodal call dengan SEMUA foto sekaligus. Hemat quota,
     * hasil draft koheren.
     */
    private function analyzeBatchImages(array $images, string $caption): ?array
    {
        try {
            $categories = Category::where('is_active', true)->pluck('name')->implode(', ');
            $promptTemplate = Setting::get('prompt_ad_builder_polish', '');
            if (trim($promptTemplate) === '') {
                $promptTemplate = 'Kamu copywriter Marketplace Jamaah. Lihat SEMUA foto produk + caption "{caption}". '
                    . 'Buat 1 draft iklan terpadu yang mempertahankan URL produk & kontak dan buang kalimat instruksi meta. '
                    . 'Kategori tersedia: {categories}. '
                    . 'Jawab HANYA JSON: {"title":"...","description":"...","price":0,"price_label":"","price_type":"fix","category":"...","condition":"baru","location":"","notes":""}';
                Log::warning('AdBuilderAgent: prompt_ad_builder_polish empty, using fallback');
            }
            // Tambahan instruksi multi-image + ATURAN HARGA & TIPE HARGA yang KETAT.
            // Ditambah selalu (bukan menggantikan setting) supaya prompt user-defined
            // tetap dipakai sebagai kerangka utama, plus rule price normalisasi yang
            // dulu sering keliru (mis. "Rp 975 ribu" diparse 975 rupiah, atau
            // "/ bulan" tidak dideteksi sebagai langganan).
            $multiImageHint = "\n\nCATATAN: ada " . count($images) . " foto produk yang dilampirkan. "
                . "Foto pertama adalah cover utama. Pertimbangkan info dari SEMUA foto (cover, halaman pricing, halaman spesifikasi, dll) saat membuat title, description, dan extract harga/spek/kondisi. "
                . "Hasilkan SATU draft iklan terpadu, bukan beberapa."
                . "\n\nATURAN HARGA (WAJIB DIIKUTI):"
                . "\n- price WAJIB angka bulat dalam RUPIAH PENUH. Jangan tulis 975 untuk maksud 975 ribu — tulis 975000."
                . "\n- 'Rp 975', '975rb', '975 ribu', '975K', 'Rp975K' → price = 975000."
                . "\n- '1,5 juta', '1.5jt', '1500K', 'Rp1.500.000' → price = 1500000."
                . "\n- '25K' / '25rb' → price = 25000. '250rb' → 250000."
                . "\n- Kalau harga tidak eksplisit di gambar/caption → price = 0, price_label = ''."
                . "\n- price_label = string yang menampilkan harga formatted (mis. 'Rp 975.000 / bulan'), boleh dengan suffix '/bulan', '/tahun', dst sesuai konteks."
                . "\n\nATURAN price_type (WAJIB DIIKUTI):"
                . "\n- 'langganan' kalau ada indikasi BERLANGGANAN/RECURRING: kata kunci 'per bulan', '/bulan', 'monthly', 'tahunan', '/tahun', 'per tahun', 'subscription', 'langganan', 'mulai Rp X /bulan', 'starts from /month'."
                . "\n- 'nego' kalau ada 'nego', 'negotiable', 'harga nego', 'hubungi penjual', 'PM/DM untuk harga'."
                . "\n- 'lelang' kalau ada 'lelang', 'auction', 'bid'."
                . "\n- 'fix' DEFAULT — hanya kalau benar2 harga tunggal sekali bayar dan tidak match di atas.";
            $prompt = str_replace(
                ['{caption}', '{categories}'],
                [$caption ?: 'tidak ada keterangan dari penjual', $categories],
                $promptTemplate
            ) . $multiImageHint;

            $result = $this->gemini->analyzeMultipleImagesWithText($images, $prompt);
            if (!$result) {
                return null;
            }
            $clean = preg_replace('/```json\s*/i', '', $result);
            $clean = preg_replace('/```\s*/i', '', $clean);
            $draft = json_decode(trim($clean), true);
            if (!is_array($draft)) {
                return null;
            }
            return $this->normalizeDraftPrice($draft);
        } catch (\Throwable $e) {
            Log::warning('AdBuilderAgent::analyzeBatchImages failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Safety net post-process untuk price & price_type. AI kadang masih salah
     * meskipun prompt sudah explicit — di sini kita coba normalisasi
     * deterministik berdasar price_label & description.
     */
    private function normalizeDraftPrice(array $draft): array
    {
        $label = (string) ($draft['price_label'] ?? '');
        $desc  = (string) ($draft['description'] ?? '');
        $combined = $label . ' ' . $desc;
        $price = (int) ($draft['price'] ?? 0);

        // (1) Auto-detect price_type langganan dari pola '/bulan' / '/tahun' / 'monthly'
        $type = strtolower((string) ($draft['price_type'] ?? 'fix'));
        if ($type === 'fix' || $type === '') {
            if (preg_match('/(\/\s*(bulan|bln|tahun|thn|th|month|year)\b|per\s+(bulan|tahun|bln|thn|month|year)\b|monthly|yearly|subscription|langganan)/iu', $combined)) {
                $draft['price_type'] = 'langganan';
            } elseif (preg_match('/\b(nego|negotiable|hub(?:ungi)?\s+penjual|pm\s+harga|dm\s+harga)\b/iu', $combined)) {
                $draft['price_type'] = 'nego';
            } elseif (preg_match('/\b(lelang|auction|bid)\b/iu', $combined)) {
                $draft['price_type'] = 'lelang';
            }
        }

        // (2) Kalau price kelihatan terlalu kecil padahal label menyebut 'ribu/rb/K',
        // koreksi dengan extract dari label.
        if ($price > 0 && $price < 1000 && preg_match('/(\d[\d.,]*)\s*(rb|ribu|k)\b/iu', $label, $m)) {
            $n = (int) preg_replace('/[^0-9]/', '', $m[1]);
            if ($n > 0) {
                $draft['price'] = $n * 1000;
            }
        }
        // Sama untuk 'juta/jt'
        if ((int) ($draft['price'] ?? 0) < 1000 && preg_match('/(\d[\d.,]*)\s*(jt|juta)\b/iu', $label, $m)) {
            $n = (float) str_replace(',', '.', preg_replace('/[^\d,\.]/u', '', $m[1]));
            if ($n > 0) {
                $draft['price'] = (int) round($n * 1000000);
            }
        }

        return $draft;
    }

    /**
     * @deprecated kept for backward-compat path di handleImage (enriching step).
     * Helper: analyze 1 image dengan Gemini, return parsed draft array atau null.
     */
    private function analyzeSingleImageWithCaption(?string $url, ?string $path, ?string $mime, string $caption): ?array
    {
        try {
            $base64 = null;
            $mimeType = $mime ?: 'image/jpeg';
            // Prefer disk file — paling reliable (URL WhaCenter bisa expired).
            if ($path && Storage::disk('local')->exists($path)) {
                $base64 = base64_encode(Storage::disk('local')->get($path));
            } elseif ($url) {
                $resp = Http::timeout(15)->get($url);
                if ($resp->failed()) {
                    Log::warning('AdBuilderAgent::analyzeSingleImageWithCaption url fetch failed', [
                        'url' => $url, 'status' => $resp->status(),
                    ]);
                    return null;
                }
                $base64 = base64_encode($resp->body());
                $mimeType = $resp->header('Content-Type') ?: $mimeType;
            } else {
                Log::warning('AdBuilderAgent::analyzeSingleImageWithCaption no path nor url');
                return null;
            }

            $categories = Category::where('is_active', true)->pluck('name')->implode(', ');
            $promptTemplate = Setting::get('prompt_ad_builder_polish', '');
            if (trim($promptTemplate) === '') {
                // Fallback minimum supaya tidak gagal silent kalau setting kosong
                $promptTemplate = 'Analisa foto + caption "{caption}". Buat draft iklan marketplace. Pertahankan URL produk & kontak, buang kalimat instruksi meta. Kategori tersedia: {categories}. Jawab JSON: {"title":"...","description":"...","price":0,"price_label":"","price_type":"fix","category":"...","condition":"baru","location":"","notes":""}';
                Log::warning('AdBuilderAgent: prompt_ad_builder_polish empty, using fallback');
            }
            $prompt = str_replace(
                ['{caption}', '{categories}'],
                [$caption ?: 'tidak ada keterangan dari penjual', $categories],
                $promptTemplate
            );

            $result = $this->gemini->analyzeImageWithText($base64, $mimeType, $prompt);
            if (!$result) {
                return null;
            }
            $clean = preg_replace('/```json\s*/i', '', $result);
            $clean = preg_replace('/```\s*/i', '', $clean);
            $draft = json_decode(trim($clean), true);
            return is_array($draft) ? $draft : null;
        } catch (\Throwable $e) {
            Log::warning('AdBuilderAgent::analyzeSingleImageWithCaption failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Re-show current draft preview. Dipanggil dari handleEnriching/handleReview
     * saat user ketik 'preview' / 'lihat' / 'tampilkan'.
     */
    private function showCurrentPreview(string $phone, array $state): string
    {
        $draft = $state['draft'] ?? null;
        if (!$draft || empty($draft['title'])) {
            return '⚠️ Draft belum ada. Kirim foto produk dulu ya.';
        }
        $this->pushPreview($phone, $draft, $this->formatDraftPreview($draft, !empty($state['on_behalf_pasmal'])));
        return '';
    }

    public function handleEnriching(string $phone, string $text, array $state): string
    {
        $draft = $state['draft'] ?? [];

        if ($this->isCancelCommand($text)) {
            $this->cancelSession($phone);
            return "❌ *Pembuatan iklan dibatalkan.*\n\nKetik *buat iklan* kapan saja untuk memulai lagi.";
        }

        // Detect "on behalf pasmal" at any enriching step
        if (self::isOnBehalfPasmal($text)) {
            $state['on_behalf_pasmal'] = true;
            $this->putState($phone, $state);
            return "✅ _Mode atas nama *Pasmal* aktif._\n\nLanjutkan dengan info produk, atau ketik *lanjut* untuk review.";
        }

        // Meta-request: user is asking to see / include the image in the preview — re-send preview with image.
        if ($this->isShowImageRequest($text)) {
            $this->pushPreview($phone, $draft, $this->formatEnrichPrompt($draft));
            return '';
        }

        // "preview" / "lihat" / "tampilkan" → re-send full preview
        if (preg_match('/^\s*(preview|lihat|liat|tampilkan|tampilin|cek|show)\s*$/iu', $text)) {
            return $this->showCurrentPreview($phone, $state);
        }

        // "lanjut", "skip", "tidak ada", "sudah ok" → proceed to review as-is
        if (preg_match('/^\s*(lanjut|next|skip|oke|ok|sudah|sudah\s+ok|tidak\s+ada|ga\s+ada|gak\s+ada|langsung\s+post)\s*$/iu', $text)) {
            $newState = ['step' => 'reviewing', 'draft' => $draft];
            if (!empty($state['on_behalf_pasmal'])) $newState['on_behalf_pasmal'] = true;
            $this->putState($phone, $newState);
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
            . '- Jika ada deskripsi/spek baru → TAMBAHKAN ke description (boleh ditambah, tidak boleh dikurangi). Pertahankan SEMUA isi description sebelumnya tanpa memotong; integrasikan info baru sebagai tambahan, bukan pengganti.' . "\n"
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
        $this->putState($phone, $newState);
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
            $this->cancelSession($phone);
            return "❌ *Pembuatan iklan dibatalkan.*\n\nKetik *buat iklan* kapan saja untuk memulai lagi.";
        }

        // Meta-request: user asks to display image in preview → re-send preview with photo.
        if ($this->isShowImageRequest($text)) {
            $this->pushPreview($phone, $draft, $this->formatDraftPreview($draft, $onBehalfPasmal));
            return '';
        }

        // "preview" / "lihat" / "tampilkan" → re-send full preview
        if (preg_match('/^\s*(preview|lihat|liat|tampilkan|tampilin|cek|show)\s*$/iu', $text)) {
            return $this->showCurrentPreview($phone, $state);
        }

        // Detect "on behalf pasmal" at review step
        if (self::isOnBehalfPasmal($text)) {
            $state['on_behalf_pasmal'] = true;
            $onBehalfPasmal = true;
            $this->putState($phone, $state);
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
                    elseif (preg_match('/\b(langganan|subscription|monthly|bulanan|tahunan|berlangganan|trial)\b/iu', $lv)) $draft['price_type'] = 'langganan';
                    elseif (preg_match('/\b(fix|tetap)\b/iu', $lv)) $draft['price_type'] = 'fix';
                    $clean = preg_replace('/\b(nego|fix|tetap|lelang|auction|langganan|subscription|monthly|bulanan|tahunan|berlangganan)\b\s*/iu', '', $value);
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
                $this->putState($phone, $updState);
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
            . '- Jika menyebut info pengiriman, garansi, kelengkapan, syarat order → TAMBAHKAN ke description. WAJIB pertahankan SEMUA isi description lama; tambahkan info baru tanpa memotong/mengganti yang sudah ada.' . "\n"
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
        $this->putState($phone, $newState);

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
        // Terima banyak variasi: batal/batalkan/batalin, cancel, stop, keluar,
        // ga jadi/gak jadi/tidak jadi/nggak jadi, sudahan, lupakan, tutup
        return (bool) preg_match(
            '/^\s*(batal(kan|in)?|cancel|hapus|stop|keluar|sudahan|sudahin|lupakan|tutup|kelar(in)?|(ga|gak|nggak|gk|g|tidak|tdk)\s*jadi)\s*$/iu',
            $text
        );
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
                'nego'      => "{$rp} (Nego)",
                'lelang'    => "Lelang mulai {$rp}",
                'langganan' => "{$rp} / bulan",
                default     => $rp,
            };
        }
        if (!$priceStr) {
            $priceStr = match ($priceType) {
                'nego'      => 'Harga Nego',
                'lelang'    => 'Harga Lelang',
                'langganan' => 'Lihat detail di deskripsi',
                default     => 'Belum dicantumkan',
            };
        }

        $priceTypeLabel = match ($priceType) {
            'nego'      => '🤝 Nego',
            'lelang'    => '🔨 Lelang',
            'langganan' => '🔁 Langganan',
            default     => '🏷️ Fix',
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
            'media_urls' => !empty($draft['media_urls']) && is_array($draft['media_urls'])
                ? array_values(array_unique($draft['media_urls']))
                : (!empty($draft['media_url']) ? [$draft['media_url']] : null),
            'location' => $draft['location'] ?? null,
            'condition' => $condition,
            'status' => 'active',
            'source_date' => now(),
        ]);

        // Clear state BEFORE delegating — success path below uses $listing directly.
        $this->cancelSession($phone);

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

        $listingUrl = $listing->share_url;

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
