<?php
// Send apology to Regar via WA gateway
$url = 'http://localhost:3001/api/send';
$token = 'fc42fe461f106cdee387e807b972b52b';
$data = [
    'number' => '94162896556265',
    'message' => "Assalamualaikum Kak R 36 AR 🙏\n\nMohon maaf ya kak, tadi pesan video kakak di grup *Marketplace Jamaah* tidak sengaja terhapus oleh sistem bot kami karena ada kesalahan teknis.\n\nKami sudah perbaiki sistemnya. Silakan kirim ulang ya kak videonya ke grup, pastikan sertakan:\n✅ Nama produk/jasa\n✅ Harga\n✅ Deskripsi singkat\n\nSekali lagi mohon maaf atas ketidaknyamanannya 🙏\n\n_- Admin Marketplace Jamaah_"
];
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ],
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_RETURNTRANSFER => true,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP $code\n$resp\n";
