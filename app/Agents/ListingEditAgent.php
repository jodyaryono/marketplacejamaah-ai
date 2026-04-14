<?php

namespace App\Agents;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Setting;
use App\Services\GeminiService;
use App\Services\WhacenterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ListingEditAgent
{
    private string $baseUrl;

    public function __construct(
        private GeminiService $gemini,
        private WhacenterService $whacenter,
    ) {
        $this->baseUrl = rtrim(config('app.url'), '/');
    }

    // ── Mark sold ────────────────────────────────────────────────────────────

    public function markAsSold(Message $message, int $listingId): string
    {
        $phone    = $message->sender_number;
        $isMaster = MasterCommandAgent::isMasterPhone($phone);
        $contact  = Contact::where('phone_number', $phone)->first();

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
        $this->announceToGroup("✅ *TERJUAL!*\n\n📦 *{$listing->title}* (#️⃣{$listing->id})\n"
            . "👤 Penjual: " . ($listing->contact_name ?? $contact?->name ?? 'Penjual') . "\n\n_Iklan ini sudah tidak tersedia._");

        return "✅ *Iklan #{$listing->id} — {$listing->title}* berhasil ditandai *TERJUAL!*\n\n"
            . "📭 Iklan sudah dihapus dari etalase marketplace.\n\n"
            . "_Ketik *aktifkan #{$listing->id}* jika ingin mengaktifkan kembali._";
    }

    // ── Reactivate ───────────────────────────────────────────────────────────

    public function reactivateListing(Message $message, int $listingId): string
    {
        $phone    = $message->sender_number;
        $isMaster = MasterCommandAgent::isMasterPhone($phone);
        $contact  = Contact::where('phone_number', $phone)->first();

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

    // ── Show member edit menu (triggered by bare listing number) ─────────────

    public function showMemberEditMenu(Listing $listing, string $phone): string
    {
        Cache::put('edit_pending:' . $phone, [
            'listing_id'  => $listing->id,
            'member_only' => true,
        ], now()->addMinutes(10));

        $status = match ($listing->status) {
            'active'   => '🟢 Aktif (tayang)',
            'sold'     => '✅ Terjual',
            'inactive' => '🔴 Disembunyikan',
            'expired'  => '🟡 Kadaluarsa',
            default    => $listing->status,
        };

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

    // ── Apply member-limited edit (price + status only) ──────────────────────

    public function applyMemberEdit(Message $message, int $listingId, string $text): string
    {
        if (preg_match('/^\s*(batal|cancel)\s*$/iu', $text)) {
            return '👌 Oke, tidak ada yang diubah.';
        }

        $phone    = $message->sender_number;
        $contact  = Contact::where('phone_number', $phone)->first();
        $isMaster = MasterCommandAgent::isMasterPhone($phone);

        $listing = Listing::find($listingId);
        if (!$listing) {
            return "❌ Iklan #{$listingId} tidak ditemukan.";
        }

        if (!$isMaster) {
            $isOwner = ($contact && $listing->contact_id === $contact->id)
                || $listing->contact_number === $phone;
            if (!$isOwner) {
                return "🚫 Kamu tidak bisa mengubah iklan ini karena bukan milikmu.";
            }
        }

        $lower   = mb_strtolower(trim($text));
        $changes = [];
        $desc    = [];

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
        } elseif (preg_match('/^\s*harga\s+(.+)$/iu', $text, $hm) || preg_match('/^\s*(\d[\d.,]*[kKmM]?)\s*$/u', $text, $hm)) {
            $rawPrice  = trim($hm[1]);
            $priceType = 'fix';

            if (preg_match('/\b(nego|negotiable|negosiasi)\b/iu', $rawPrice)) {
                $priceType = 'nego';
                $rawPrice  = trim(preg_replace('/\b(nego|negotiable|negosiasi)\b\s*/iu', '', $rawPrice));
            } elseif (preg_match('/\b(lelang|auction|bid)\b/iu', $rawPrice)) {
                $priceType = 'lelang';
                $rawPrice  = trim(preg_replace('/\b(lelang|auction|bid)\b\s*/iu', '', $rawPrice));
            } elseif (preg_match('/\b(fix|fixed|tetap|pasti)\b/iu', $rawPrice)) {
                $priceType = 'fix';
                $rawPrice  = trim(preg_replace('/\b(fix|fixed|tetap|pasti)\b\s*/iu', '', $rawPrice));
            }

            $changes['price_type']  = $priceType;
            $changes['price_label'] = null;

            $numericValue = $this->parsePrice($rawPrice);

            if ($numericValue && $numericValue > 0) {
                $changes['price'] = $numericValue;
                $rpStr            = 'Rp ' . number_format($numericValue, 0, ',', '.');
                $typeLabel        = match ($priceType) {
                    'nego'   => "(Nego)",
                    'lelang' => "— Harga Lelang",
                    default  => "(Fix)",
                };
                $desc[] = "Harga → *{$rpStr} {$typeLabel}*";
            } elseif ($priceType !== 'fix') {
                $changes['price'] = null;
                $typeLabel        = match ($priceType) {
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

        $link = "{$this->baseUrl}/p/{$listing->id}";

        if (($changes['status'] ?? '') === 'sold') {
            $this->announceToGroup("✅ *TERJUAL!*\n\n📦 *{$listing->title}* (#️⃣{$listing->id})\n"
                . "👤 Penjual: " . ($listing->contact_name ?? $contact?->name ?? 'Penjual') . "\n\n_Iklan ini sudah tidak tersedia._");
            return "✅ *Iklan #{$listing->id}* berhasil ditandai *TERJUAL!*\n\n"
                . "📭 Iklan sudah dihapus dari etalase marketplace.";
        }

        if (($changes['status'] ?? '') === 'inactive') {
            return "✅ *Iklan #{$listing->id}* berhasil diperbarui!\n\n"
                . implode("\n", $desc)
                . "\n\n📭 Iklan tidak lagi tayang di etalase.\n_Ketik *{$listing->id}* → *aktifkan* jika ingin tampilkan lagi._";
        }

        if ($listing->status === 'active') {
            $this->repostToGroup($listing);
        }

        return "✅ *Iklan #{$listing->id}* berhasil diperbarui!\n\n"
            . implode("\n", $desc)
            . "\n\n🔗 {$link}";
    }

    // ── Full edit via DM ─────────────────────────────────────────────────────

    public function handleEditListing(Message $message, int $listingId, string $editText): string
    {
        $phone    = $message->sender_number;
        $contact  = Contact::where('phone_number', $phone)->first();
        $isMaster = MasterCommandAgent::isMasterPhone($phone);

        if ($listingId <= 0) {
            return $this->myListingsForEdit($phone);
        }

        $listing = Listing::with('category')->find($listingId);
        if (!$listing) {
            return "❌ Iklan #{$listingId} tidak ditemukan.\n\nKetik *iklanku* untuk melihat daftar iklan kamu.";
        }

        if (!$isMaster) {
            $isOwner = ($contact && $listing->contact_id === $contact->id) ||
                $listing->contact_number === $phone;
            if (!$isOwner) {
                return "🚫 Kamu tidak bisa mengedit iklan #{$listingId} karena bukan milikmu.";
            }
        }

        $cleanText = preg_replace('/^(?:edit|ubah|perbarui|update)\s*(?:iklan|listing|#)?\s*#?\d+\s*/iu', '', $editText);
        $cleanText = trim($cleanText ?: $editText);

        if (empty($cleanText)) {
            Cache::put('edit_pending:' . $phone, ['listing_id' => $listingId], now()->addMinutes(5));
            return $this->formatListingForEdit($listing);
        }

        return $this->parseAndApplyEdits($listing, $cleanText, $phone);
    }

    public function applyPendingEdit(Message $message, int $listingId, string $text): string
    {
        if (preg_match('/^\s*(batal|cancel|ga\s*jadi|tidak|no)\s*$/i', $text)) {
            return '👌 Edit dibatalkan.';
        }

        $listing = Listing::with('category')->find($listingId);
        if (!$listing) {
            return "❌ Iklan #{$listingId} tidak ditemukan.";
        }

        $phone    = $message->sender_number;
        $contact  = Contact::where('phone_number', $phone)->first();
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

    public function formatListingForEdit(Listing $listing): string
    {
        $cat = $listing->category?->name ?? 'Belum dikategorikan';
        $condition = match ($listing->condition) {
            'new'   => 'Baru',
            'used'  => 'Bekas',
            default => 'Tidak diketahui'
        };
        $status = match ($listing->status) {
            'active'  => '🟢 Aktif',
            'sold'    => '✅ Terjual',
            'expired' => '🟡 Kadaluarsa',
            default   => '⚪ ' . $listing->status
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

    public function myListingsForEdit(string $phoneNumber): string
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
            $status  = match ($listing->status) {
                'active' => '🟢',
                'sold'   => '✅',
                default  => '🟡',
            };
            $lines[] = "{$status} *#{$listing->id}* — {$listing->title}\n   💰 {$listing->price_formatted}";
        }
        $lines[] = "\nKetik: *edit #<nomor>*\nContoh: *edit #" . $listings->first()->id . '*';

        return implode("\n", $lines);
    }

    // ── Parse & apply edits via Gemini ───────────────────────────────────────

    public function parseAndApplyEdits(Listing $listing, string $editText, ?string $senderPhone = null): string
    {
        $isMaster = $senderPhone && MasterCommandAgent::isMasterPhone($senderPhone);

        $onBehalfPasmal = $isMaster && AdBuilderAgent::isOnBehalfPasmal($editText);
        $editText = trim(preg_replace('/\b(on\s*behalf\s*pasmal|atas\s*nama\s*pasmal|behalf\s*pasmal)\b[,\s]*/iu', '', $editText));
        $editText = trim($editText, ' ,');

        // Pure "on behalf pasmal" — just reassign contact
        if (empty($editText) && $onBehalfPasmal) {
            return $this->applyOnBehalfPasmal($listing);
        }

        // Generalized "on behalf <Name> <phone>" / "atas nama <Name> <phone>" — master-only.
        $onBehalfContact = null;
        if ($isMaster) {
            $onBehalfContact = self::parseOnBehalfArbitrary($editText);
            if ($onBehalfContact) {
                $editText = trim(preg_replace(
                    '/\b(?:on\s*behalf|atas\s*nama|a\.?\s*n\.?)\s+.+?\s+(?:\+?62|0)[\d\s\-]{7,17}[,\s]*/iu',
                    '',
                    $editText
                ));
                $editText = trim($editText, ' ,');

                // Pure "on behalf X <phone>" — just reassign contact, no Gemini needed.
                if (empty($editText)) {
                    return $this->applyOnBehalfContact($listing, $onBehalfContact['name'], $onBehalfContact['phone']);
                }
            }
        }

        $cleanExistingDesc = trim(preg_replace('/\b(on\s*behalf\s*pasmal|atas\s*nama\s*pasmal|behalf\s*pasmal)\b[.,\s]*/iu', '', $listing->description ?? ''));

        $categories = Category::where('is_active', true)->pluck('name', 'id')->toArray();
        $catList    = implode(', ', array_map(fn($id, $name) => "{$id}:{$name}", array_keys($categories), $categories));

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

        // Sanitize description from Gemini
        if (!empty($parsed['description'])) {
            $parsed['description'] = trim(preg_replace('/\b(on\s*behalf\s*pasmal|atas\s*nama\s*pasmal|behalf\s*pasmal)\b[.,\s]*/iu', '', $parsed['description']));
        }

        // Guard: reject if Gemini returned a shorter description
        if (!empty($parsed['description']) && !empty($cleanExistingDesc)) {
            if (mb_strlen($parsed['description']) < mb_strlen($cleanExistingDesc)) {
                $parsed['description'] = null;
            }
        }

        $changes    = [];
        $changeDesc = [];
        $editable   = ['title', 'description', 'price', 'price_label', 'price_type', 'location', 'condition', 'status', 'category_id'];

        foreach ($editable as $field) {
            if (!isset($parsed[$field]) || $parsed[$field] === null) continue;
            $val = $parsed[$field];

            if ($field === 'condition' && !in_array($val, ['new', 'used', 'unknown'])) continue;
            if ($field === 'status' && !in_array($val, ['active', 'sold', 'expired', 'inactive'])) continue;
            if ($field === 'price_type' && !in_array($val, ['fix', 'nego', 'lelang'])) continue;
            if ($field === 'category_id' && !isset($categories[$val])) continue;

            $changes[$field] = $val;

            $label = match ($field) {
                'title'       => 'Judul',
                'description' => 'Deskripsi',
                'price'       => 'Harga',
                'price_label' => 'Label harga',
                'location'    => 'Lokasi',
                'condition'   => 'Kondisi',
                'status'      => 'Status',
                'price_type'  => 'Tipe Harga',
                'category_id' => 'Kategori',
                default       => $field,
            };
            $display = match ($field) {
                'price'       => 'Rp ' . number_format((int) $val, 0, ',', '.'),
                'category_id' => $categories[$val] ?? $val,
                'condition'   => match ($val) { 'new' => 'Baru', 'used' => 'Bekas', default => 'Tidak diketahui' },
                'status'      => match ($val) { 'active' => 'Aktif', 'sold' => 'Terjual', 'expired' => 'Kadaluarsa', 'inactive' => 'Disembunyikan', default => $val },
                'price_type'  => match ($val) { 'nego' => 'Nego 🤝', 'lelang' => 'Lelang 🔨', default => 'Fix 🏷️' },
                default       => Str::limit((string) $val, 100),
            };
            $changeDesc[] = "• {$label} → *{$display}*";
        }

        if ($onBehalfPasmal) {
            $this->applyOnBehalfPasmal($listing);
        }
        if ($onBehalfContact) {
            $this->applyOnBehalfContact($listing, $onBehalfContact['name'], $onBehalfContact['phone']);
            $changeDesc[] = "• Kontak → *{$onBehalfContact['name']}* ({$onBehalfContact['phone']})";
        }

        if (empty($changes) && !$onBehalfPasmal && !$onBehalfContact) {
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
        $link   = "{$this->baseUrl}/p/{$listing->id}";
        $result = "✅ *Iklan #{$listing->id} berhasil diperbarui!*\n\n"
            . implode("\n", $changeDesc) . "\n\n"
            . "🔗 {$link}";

        if (($changes['status'] ?? '') === 'sold') {
            $this->announceToGroup("✅ *TERJUAL!*\n\n📦 *{$listing->title}* (#️⃣{$listing->id})\n"
                . "👤 Penjual: " . ($listing->contact_name ?? 'Penjual') . "\n\n_Iklan ini sudah tidak tersedia._");
            return $result . "\n\n📭 _Iklan sudah dihapus dari etalase marketplace._";
        }

        if (($changes['status'] ?? '') === 'inactive') {
            return $result . "\n\n📭 _Iklan disembunyikan dari etalase._";
        }

        if ($listing->status === 'active') {
            $this->repostToGroup($listing);
        }

        return $result;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Parse "on behalf <Name> <phone>" / "atas nama <Name> <phone>" / "a.n. <Name> <phone>".
     * Returns ['name' => string, 'phone' => string (normalized to 62xxx)] or null.
     * Explicitly skips the "pasmal" alias — that flows through applyOnBehalfPasmal.
     */
    public static function parseOnBehalfArbitrary(string $text): ?array
    {
        if (!preg_match(
            '/\b(?:on\s*behalf|atas\s*nama|a\.?\s*n\.?)\s+(.+?)\s+((?:\+?62|0)[\d\s\-]{7,17})/iu',
            $text,
            $m
        )) {
            return null;
        }

        $name = trim(preg_replace('/\s+/', ' ', $m[1]));
        if ($name === '' || preg_match('/\bpasmal\b/i', $name)) {
            return null;
        }

        $raw = preg_replace('/[\s\-]/', '', $m[2]);
        if (str_starts_with($raw, '+')) $raw = substr($raw, 1);
        if (str_starts_with($raw, '0')) $raw = '62' . substr($raw, 1);
        if (!str_starts_with($raw, '62')) $raw = '62' . $raw;

        return ['name' => $name, 'phone' => $raw];
    }

    private function applyOnBehalfContact(Listing $listing, string $name, string $phone): string
    {
        $contact = Contact::where('phone_number', $phone)->first();
        $listing->update([
            'contact_number' => $phone,
            'contact_name'   => $name,
            'contact_id'     => $contact?->id,
        ]);
        $listing->refresh();
        $link = "{$this->baseUrl}/p/{$listing->id}";
        return "✅ *Iklan #{$listing->id} berhasil diperbarui!*\n\n"
            . "• Kontak → *{$name}* ({$phone})\n\n"
            . "🔗 {$link}";
    }

    private function applyOnBehalfPasmal(Listing $listing): string
    {
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

    private function announceToGroup(string $message): void
    {
        $group = \App\Models\WhatsappGroup::where('is_active', true)->first();
        if (!$group) return;
        try {
            $this->whacenter->sendGroupMessage($group->group_name, $message);
        } catch (\Exception $e) {
            Log::warning('ListingEditAgent: gagal kirim notif ke WAG', ['error' => $e->getMessage()]);
        }
    }

    private function repostToGroup(Listing $listing): void
    {
        $group = \App\Models\WhatsappGroup::where('is_active', true)->first();
        if (!$group) return;

        $link      = "{$this->baseUrl}/p/{$listing->id}";
        $priceStr  = $listing->price_formatted;
        $catLine   = $listing->category ? "📂 {$listing->category->name}\n" : '';
        $locLine   = $listing->location ? "📍 {$listing->location}\n" : '';
        $mediaUrls = $listing->media_urls ?? [];
        $firstImg  = !empty($mediaUrls) ? $mediaUrls[0] : null;
        $shortDesc = BroadcastAgent::extractWagDescription($listing->description ?? '');
        $descLine  = $shortDesc ? "_{$shortDesc}_\n" : '';

        $caption = "✏️ *[UPDATE] {$listing->title}*\n"
            . $descLine
            . "💰 {$priceStr}\n"
            . $catLine
            . $locLine
            . "\n🔗 {$link}";

        try {
            if ($firstImg) {
                $this->whacenter->sendGroupImageMessage($group->group_name, $caption, $firstImg);
            } else {
                $this->whacenter->sendGroupMessage($group->group_name, $caption);
            }
        } catch (\Exception $e) {
            Log::warning('ListingEditAgent: gagal re-post ke WAG', ['error' => $e->getMessage()]);
        }
    }

    private function parsePrice(string $raw): ?int
    {
        if (preg_match('/(\d[\d.,]*)\s*(jt|juta)/iu', $raw, $m)) {
            return (int) round((float) str_replace(['.', ','], ['', '.'], $m[1]) * 1_000_000);
        }
        if (preg_match('/(\d[\d.,]*)\s*[kK]\b/', $raw, $m)) {
            return (int) round((float) str_replace(['.', ','], ['', '.'], $m[1]) * 1_000);
        }
        if (preg_match('/^[\d.,]+$/', $raw)) {
            return (int) preg_replace('/[^\d]/', '', $raw);
        }
        return null;
    }
}
