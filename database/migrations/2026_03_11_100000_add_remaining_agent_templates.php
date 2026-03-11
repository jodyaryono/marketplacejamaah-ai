<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        // Start sort_order after existing ai_prompts (15 prompts × 10 = 150)
        $order = 150;

        $templates = [
            // ── WhatsAppListenerAgent ──
            [
                'key' => 'template_duplicate_with_listing',
                'label' => 'Listener — DM Duplikat (Ada Iklan)',
                'description' => 'Template DM ketika member kirim ulang iklan yang sudah ada. Variabel: {name}, {title}, {listingUrl}',
                'value' => 'Halo *{name}*! 🙏

Eh, kamu tadi kirim ulang iklan yang sama 😊 Duplikatnya aku hapus dari grup biar rapi, tapi tenang — iklanmu di marketplace sudah aku *refresh* biar balik ke urutan atas.

📦 *{title}*
🔗 {listingUrl}',
            ],
            [
                'key' => 'template_duplicate_no_listing',
                'label' => 'Listener — DM Duplikat (Tanpa Iklan)',
                'description' => 'Template DM ketika member kirim pesan duplikat yang bukan iklan. Variabel: {name}',
                'value' => 'Halo *{name}*! 🙏

Kamu tadi kirim pesan yang sama seperti sebelumnya 😊 Aku hapus duplikatnya biar grupnya tetap rapi ya. _Kalau iklanmu belum muncul di website, tunggu bentar atau kabarin aku._',
            ],

            // ── MessageParserAgent ──
            [
                'key' => 'config_price_regex',
                'label' => 'Parser — Regex Deteksi Harga',
                'description' => 'Pattern regex (case-insensitive) untuk mendeteksi apakah pesan mengandung harga. Contoh match: Rp 50.000, 100rb, 2juta, harga 500k',
                'value' => '(?:rp\.?\s*[\d.,]+|harga\s*[\d.,]+|[\d.,]+\s*(?:ribu|rb|juta|k))',
            ],
            [
                'key' => 'config_contact_regex',
                'label' => 'Parser — Regex Deteksi Kontak',
                'description' => 'Pattern regex (case-insensitive) untuk mendeteksi nomor telepon/kontak dalam pesan. Contoh match: wa 08123456789, hub 6281234567890',
                'value' => '(?:wa|whatsapp|hub|telp|hp|contact|call)?\s*(?::|\s)?\s*(?:\+?62|0)[0-9\s\-]{8,14}',
            ],

            // ── BroadcastAgent ──
            [
                'key' => 'template_broadcast_new_listing',
                'label' => 'Broadcast — Notifikasi Iklan Baru ke Grup',
                'description' => 'Template notifikasi ke grup WA saat iklan baru masuk. Variabel: {senderName}, {title}, {categoryName}, {priceLabel}, {locationLine}, {paddedId}, {listingUrl}',
                'value' => '✅ *Iklan Diterima!*

Halo *{senderName}*, iklan kamu sudah masuk ke Marketplace Jamaah! 🎉

📋 *{title}*
📦 Kategori: {categoryName}
💰 Harga: {priceLabel}
{locationLine}🔢 ID Iklan: #{paddedId}

🔗 Lihat & bagikan: {listingUrl}

_Calon pembeli kini bisa menemukan iklanmu. Terima kasih sudah bergabung!_ 🙏',
            ],

            // ── GroupAdminReplyAgent ──
            [
                'key' => 'template_listing_dm',
                'label' => 'Admin Reply — DM Konfirmasi ke Penjual',
                'description' => 'Template DM ke penjual saat iklan berhasil tayang. Variabel: {title}, {categoryName}, {priceLabel}, {locationLine}, {paddedId}, {listingUrl}',
                'value' => '✅ *Iklan kamu sudah tayang!*

📋 *{title}*
📦 Kategori: {categoryName}
💰 Harga: {priceLabel}
{locationLine}🔢 ID Iklan: #{paddedId}

🔗 *Link produkmu di web:*
{listingUrl}

Bagikan link ini ke calon pembeli, atau share langsung dari halaman produknya! 🛍️',
            ],
            [
                'key' => 'template_escalation_report',
                'label' => 'Admin Reply — Laporan Eskalasi ke Admin',
                'description' => 'Template laporan pelanggaran ke admin saat member kena 3 peringatan. Variabel: {senderName}, {senderNumber}, {category}, {severityLabel}, {violationReason}, {totalViolations}, {datetime}, {excerpt}',
                'value' => '🚨 *LAPORAN PELANGGARAN*

👤 Pelanggar : *{senderName}*
📞 Nomor     : {senderNumber}
🏷️ Kategori  : {category}
⚠️ Tingkat   : {severityLabel}
📝 Alasan    : {violationReason}
🔢 Total Pelanggaran: {totalViolations}
🕐 Waktu     : {datetime}

💬 Pesan terakhir:
"{excerpt}"

🚫 *Status sistem*: Anggota ini telah *DIBLOKIR* dan bot mencoba mengeluarkannya dari grup secara otomatis.
Jika gagal, silakan keluarkan manual: {senderNumber}',
            ],
        ];

        $rows = [];
        foreach ($templates as $t) {
            $order += 10;
            $rows[] = [
                'key' => $t['key'],
                'group' => 'ai_prompts',
                'label' => $t['label'],
                'description' => $t['description'],
                'value' => $t['value'],
                'type' => str_starts_with($t['key'], 'config_') ? 'text' : 'textarea',
                'is_public' => false,
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('settings')->insert($rows);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'template_duplicate_with_listing',
            'template_duplicate_no_listing',
            'config_price_regex',
            'config_contact_regex',
            'template_broadcast_new_listing',
            'template_listing_dm',
            'template_escalation_report',
        ])->delete();
    }
};
