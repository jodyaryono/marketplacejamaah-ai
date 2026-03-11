<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Use full LID JIDs to bypass @c.us normalization (these are LID-based WA accounts)
$gatewayUrl = rtrim(config('services.wa_gateway.url'), '/');
$gatewayToken = config('services.wa_gateway.token', '');

function sendDM($gatewayUrl, $gatewayToken, $jid, $message): array {
    $response = \Illuminate\Support\Facades\Http::timeout(15)
        ->withHeaders(['Authorization' => 'Bearer ' . $gatewayToken])
        ->post($gatewayUrl . '/api/send', [
            'number' => $jid,   // pass full JID with @lid — numberToJid() returns as-is if includes @
            'message' => $message,
        ]);
    return $response->json() ?? ['error' => 'no response'];
}

$msgHamba = "Assalamu'alaikum warahmatullahi wabarakatuh 🙏\n\n"
    . "Mohon maaf yang sebesar-besarnya atas keteledoran kami 🙏\n\n"
    . "Foto iklan Anda untuk listing *Tanah SHM 7.600m² Dekat Gunung Sunda* "
    . "(Rp 4,4 Miliyar) sempat terhapus secara otomatis akibat bug pada sistem kami yang sudah kami perbaiki.\n\n"
    . "✅ Iklan teks Anda sudah *tersimpan dan aktif* di marketplace kami.\n"
    . "❌ Namun foto-foto belum terlampir.\n\n"
    . "Mohon berkenan kirim ulang foto-foto tanah tersebut ke grup, insya Allah sistem akan langsung otomatis melampirkannya ke iklan Anda.\n\n"
    . "Jazakallahu khairan 🙏";

echo "Sending to Hamba Alloh (113335764791545@lid)...\n";
$r1 = sendDM($gatewayUrl, $gatewayToken, '113335764791545@lid', $msgHamba);
echo "Result: " . json_encode($r1) . "\n\n";

sleep(2);

$msgSuryono = "Assalamu'alaikum warahmatullahi wabarakatuh 🙏\n\n"
    . "Mohon maaf yang sebesar-besarnya atas keteledoran kami 🙏\n\n"
    . "Foto iklan Anda untuk listing *Layanan Berbagai Jenis Asuransi* sempat terhapus secara otomatis akibat bug pada sistem kami yang sudah kami perbaiki.\n\n"
    . "✅ Iklan teks Anda sudah *tersimpan dan aktif* di marketplace kami.\n"
    . "❌ Namun foto belum terlampir.\n\n"
    . "Mohon berkenan kirim ulang foto iklan asuransi tersebut ke grup, insya Allah sistem akan langsung otomatis melampirkannya ke iklan Anda.\n\n"
    . "Jazakallahu khairan 🙏";

echo "Sending to Suryono (93793663627297@lid)...\n";
$r2 = sendDM($gatewayUrl, $gatewayToken, '93793663627297@lid', $msgSuryono);
echo "Result: " . json_encode($r2) . "\n\n";

echo "DONE\n";
