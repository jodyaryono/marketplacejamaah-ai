<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$wa = app(App\Services\WhacenterService::class);

$msg = "🙏 *Assalamu'alaikum, Mohon Maaf dari Admin!*\n\n"
    . "Halo seluruh anggota Marketplace Jamaah yang kami hormati,\n\n"
    . "Kami mohon maaf atas pesan pengumuman sebelumnya yang tampil tidak sempurna (muncul tanda ????). Itu adalah kendala teknis dari sisi kami.\n\n"
    . "───────────────────\n"
    . "🤖 *Kenalan dengan Admin AI Kami!*\n\n"
    . "Grup ini dikelola oleh *Jamaah Bot* — sebuah sistem kecerdasan buatan (AI) yang dirancang khusus untuk:\n\n"
    . "• Mendeteksi dan menyimpan iklan anggota secara otomatis\n"
    . "• Menjaga ketertiban grup dari spam & konten tidak relevan\n"
    . "• Menampilkan semua iklan di website marketplace\n"
    . "• Membantu anggota mencari produk via pesan pribadi\n\n"
    . "_Di balik bot ini ada tim manusia yang siap membantu jika ada kendala._ 🙏\n\n"
    . "───────────────────\n"
    . "📌 *Cara Posting Iklan yang Benar:*\n\n"
    . "Agar iklan kamu tersimpan di database dan tampil di website, pastikan setiap iklan mengandung:\n\n"
    . "✅ *1. Nama & Deskripsi Produk/Jasa*\n"
    . "   Jelaskan apa yang kamu jual/tawarkan\n\n"
    . "✅ *2. Spesifikasi Lengkap*\n"
    . "   Ukuran, warna, bahan, kondisi (baru/bekas), dll.\n\n"
    . "✅ *3. Harga yang Jelas*\n"
    . "   Cantumkan nominal harga, contoh: _Rp 150.000/pcs_\n\n"
    . "✅ *4. Foto Produk* (sangat disarankan)\n"
    . "   Kirim foto bersama teks keterangan dalam satu pesan\n\n"
    . "✅ *5. Kontak / Cara Pemesanan*\n"
    . "   Nomor WA untuk dihubungi atau tulis _\"hubungi saya di sini\"_\n\n"
    . "❌ *Jangan hanya kirim gambar saja tanpa keterangan!*\n"
    . "_Iklan tanpa deskripsi & harga tidak akan terdaftar di sistem._\n\n"
    . "*Contoh iklan yang baik:*\n"
    . "_\"Jual Gamis Syar'i Wanita, bahan Silk Velvet, ukuran M-XL, warna navy & hitam. Kondisi baru. Harga Rp 185.000. Order/info: 0812-xxxx-xxxx\"_\n\n"
    . "───────────────────\n"
    . "Terima kasih atas pengertian dan dukungan semua anggota! 💚\n\n"
    . '— _Admin Marketplace Jamaah AI_';

$result = $wa->sendGroupMessage('Marketplace Jamaah', $msg);
echo json_encode($result) . PHP_EOL;
