<?php
chdir('/var/www/marketplacejamaah-ai.jodyaryono.id');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$wa = app(\App\Services\WhacenterService::class);
$groupId = '6285719195627-1540340459@g.us';

$msg = implode("\n", [
    '\u2705 *Update Iklan - Marble Cake Premium*',
    '',
    'Halo *Yuli* \ud83d\ude4f',
    '',
    'Iklan ke-2 kamu juga sudah masuk ke Marketplace Jamaah!',
    '',
    '\ud83c\udf82 *Marble Cake Premium Ready 22 Mei - Berbagai Ukuran*',
    '\ud83d\udce6 Kategori: Makanan & Minuman',
    '\ud83d\udcb0 Harga: 55k, 110k & 200k',
    '\ud83d\udccd Bintaro sek. 7',
    '\ud83d\udd22 ID Iklan: #00034',
    '',
    '\ud83d\udd17 Lihat & bagikan: https://marketplacejamaah-ai.jodyaryono.id/p/34',
    '',
    'Calon pembeli kini bisa menemukan iklanmu. Terima kasih sudah bergabung! \ud83d\ude4f',
]);

$result = $wa->sendMessage($groupId, $msg);
echo 'Result: ' . json_encode($result) . "\n";
