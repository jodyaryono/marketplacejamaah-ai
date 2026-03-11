<?php

namespace Database\Seeders;

use App\Models\SystemMessage;
use Illuminate\Database\Seeder;

class SystemMessageSeeder extends Seeder
{
    public function run(): void
    {
        $messages = [
            [
                'key' => 'onboarding.welcome',
                'group' => 'onboarding',
                'label' => 'Pesan Sambutan Pendaftaran',
                'description' => 'Dikirim ke anggota baru saat pertama kali berinteraksi dengan bot dari grup.',
                'body' => "Halo {name}! 👋\n\nSelamat datang di *MarketplaceJamaah*. Silakan daftarkan diri Anda dengan format:\n\n*Nama Lengkap*\n*Penjual/Pembeli*\n\nContoh:\nJody Aryono\nPenjual",
                'placeholders' => ['name'],
                'sort_order' => 1,
            ],
            [
                'key' => 'onboarding.parse_retry',
                'group' => 'onboarding',
                'label' => 'Format Tidak Dikenali',
                'description' => 'Dikirim saat bot tidak dapat mengenali format pendaftaran yang dikirim.',
                'body' => "Maaf, format pesan Anda belum bisa saya kenali. 🙏\n\nSilakan kirim ulang dengan format:\n\n*Nama Lengkap*\n*Penjual/Pembeli*\n\nContoh:\nJody Aryono\nPenjual",
                'placeholders' => [],
                'sort_order' => 2,
            ],
            [
                'key' => 'onboarding.success',
                'group' => 'onboarding',
                'label' => 'Konfirmasi Pendaftaran Berhasil',
                'description' => 'Dikirim setelah nama dan role berhasil tersimpan, sebelum pertanyaan produk.',
                'body' => "✅ Pendaftaran berhasil!\n\nData Anda:\n*Nama*: {name}\n*No. HP*: {phone}\n*Role*: {role}\n\nSelangkah lagi... 👇",
                'placeholders' => ['name', 'phone', 'role'],
                'sort_order' => 3,
            ],
            [
                'key' => 'onboarding.ask_seller_products',
                'group' => 'onboarding',
                'label' => 'Pertanyaan Produk Penjual',
                'description' => 'Dikirim setelah pendaftaran berhasil dengan role Penjual.',
                'body' => "🏪 *Satu pertanyaan lagi!*\n\nSebagai Penjual, produk apa yang kamu jual?\n\n_Contoh: Hijab, Gamis, Tas, Sepatu, Kurma, dll._",
                'placeholders' => [],
                'sort_order' => 4,
            ],
            [
                'key' => 'onboarding.ask_buyer_products',
                'group' => 'onboarding',
                'label' => 'Pertanyaan Produk Pembeli',
                'description' => 'Dikirim setelah pendaftaran berhasil dengan role Pembeli.',
                'body' => "🛍️ *Satu pertanyaan lagi!*\n\nSedang mencari produk apa di Marketplace Jamaah?\n\n_Contoh: Baju Muslim, Perlengkapan Sholat, Makanan Halal, dll._",
                'placeholders' => [],
                'sort_order' => 5,
            ],
            [
                'key' => 'onboarding.ask_both_products',
                'group' => 'onboarding',
                'label' => 'Pertanyaan Produk Penjual & Pembeli',
                'description' => 'Dikirim setelah pendaftaran berhasil dengan role Keduanya.',
                'body' => "🏪🛍️ *Satu pertanyaan lagi!*\n\nKamu terdaftar sebagai Penjual & Pembeli.\nProduk apa yang kamu *jual* dan *cari*?\n\n_Contoh: Jual Hijab dan Gamis, Cari Mukena dan Sajadah_",
                'placeholders' => [],
                'sort_order' => 6,
            ],
            [
                'key' => 'onboarding.products_saved',
                'group' => 'onboarding',
                'label' => 'Konfirmasi Produk Tersimpan',
                'description' => 'Dikirim setelah info produk berhasil disimpan. Pendaftaran selesai.',
                'body' => "✅ Terima kasih! Info produkmu sudah disimpan.\n\n*Selamat bergabung di Marketplace Jamaah!* 🎉\n_Kamu sekarang bisa mulai bertransaksi di grup._",
                'placeholders' => ['products'],
                'sort_order' => 7,
            ],
            [
                'key' => 'broadcast.new_listing',
                'group' => 'broadcast',
                'label' => 'Notifikasi Iklan Baru ke Grup',
                'description' => 'Dikirim ke grup WhatsApp saat ada iklan baru yang berhasil diposting.',
                'body' => "📢 *IKLAN BARU*\n\n*{title}*\n💰 {price}\n📍 {location}\n📱 {phone}\n\n_MarketplaceJamaah_",
                'placeholders' => ['title', 'price', 'location', 'phone'],
                'sort_order' => 8,
            ],
        ];

        foreach ($messages as $data) {
            SystemMessage::updateOrCreate(['key' => $data['key']], $data);
        }
    }
}
