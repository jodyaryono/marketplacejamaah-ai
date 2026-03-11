<?php
$msg = "✅ *Update Iklan - Marble Cake Premium*\n\n"
    . "Halo *Yuli* 🙏\n\n"
    . "Iklan ke-2 kamu sudah masuk ke Marketplace Jamaah!\n\n"
    . "🎂 *Marble Cake Premium Ready 22 Mei - Berbagai Ukuran*\n"
    . "📦 Kategori: Makanan & Minuman\n"
    . "💰 Harga: 55k, 110k & 200k\n"
    . "📍 Bintaro sek. 7\n"
    . "🔢 ID Iklan: #00034\n\n"
    . "🔗 https://marketplacejamaah-ai.jodyaryono.id/p/34\n\n"
    . 'Calon pembeli kini bisa menemukan iklanmu. Terima kasih! 🙏';

$payload = json_encode([
    'group' => '6285719195627-1540340459@g.us',
    'message' => $msg,
]);

$ch = curl_init('http://localhost:3001/api/sendGroup');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer fc42fe461f106cdee387e807b972b52b',
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);
echo $result . "\n";
if ($err)
    echo "CURL ERROR: $err\n";
