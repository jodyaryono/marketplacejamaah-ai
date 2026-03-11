<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '62817799993';
$wa = app(App\Services\WhacenterService::class);

$msg = "Assalamu'alaikum Pak *Arfan Mentemas*! \xF0\x9F\x99\x8F\n\n"
    . "Selamat bergabung di *Marketplace Jamaah* \xF0\x9F\x8E\x89\n\n"
    . "Berikut panduan singkat sebagai anggota:\n\n"
    . "*\xF0\x9F\x93\xA2 Cara Pasang Iklan:*\n"
    . "Kirim pesan di grup dengan format:\n"
    . "_[JUAL/BELI] Nama Produk - Harga - Deskripsi singkat - Lokasi_\n"
    . "Contoh: JUAL Baju Koko - Rp150rb - Baru, ukuran L - Jakarta\n\n"
    . "*\xE2\x9C\x85 Aturan Grup:*\n"
    . "1. Iklan harus halal & sesuai syariat\n"
    . "2. Dilarang spam / kirim iklan berulang\n"
    . "3. Bertransaksi dengan jujur & amanah\n"
    . "4. Saling menghormati sesama anggota\n\n"
    . "*\xF0\x9F\xA4\x96 Bot Marketplace:*\n"
    . "Bot kami otomatis memproses iklan & membantu pencarian produk. "
    . "Kalau ada pertanyaan, balas pesan ini ya!\n\n"
    . "_Barakallahu fiik, semoga jadi ladang berkah \xF0\x9F\x99\x8F_";

$wa->sendMessage($phone, $msg);
echo "Guide sent to Arfan\n";
