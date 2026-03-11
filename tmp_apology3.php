<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gatewayUrl = rtrim(config('services.wa_gateway.url'), '/'); // already has /api
$gatewayToken = config('services.wa_gateway.token', '');

function sendLidDM(string $gatewayUrl, string $gatewayToken, string $lidJid, string $message): array {
    $response = \Illuminate\Support\Facades\Http::timeout(30)
        ->withHeaders(['Authorization' => 'Bearer ' . $gatewayToken])
        ->post($gatewayUrl . '/send', [
            'number' => $lidJid,
            'message' => $message,
        ]);
    return $response->json() ?? ['error' => 'no json response', 'status' => $response->status()];
}

// ── Hamba Alloh ───────────────────────────────────────────────────────────────
$msgHamba = "Assalamu'alaikum warahmatullahi wabarakatuh 🙏\n\n"
    . "Mohon maaf yang sebesar-besarnya atas keteledoran kami 🙏\n\n"
    . "Foto iklan Anda untuk listing *Tanah SHM 7.600m² Dekat Gunung Sunda* "
    . "(Rp 4,4 Miliyar) sempat terhapus secara otomatis akibat bug pada sistem kami.\n\n"
    . "✅ Iklan teks Anda sudah *tersimpan dan aktif* di marketplace kami.\n"
    . "❌ Namun foto-foto belum terlampir.\n\n"
    . "Mohon berkenan kirim ulang foto-foto tanah tersebut ke grup, "
    . "insya Allah sistem akan langsung otomatis melampirkannya ke iklan Anda. 🙏\n\n"
    . "Jazakallahu khairan 🙏";

echo "Sending to Hamba Alloh (113335764791545@lid)...\n";
$r1 = sendLidDM($gatewayUrl, $gatewayToken, '113335764791545@lid', $msgHamba);
echo "Result: " . json_encode($r1) . "\n\n";

sleep(3);

// ── Suryono ───────────────────────────────────────────────────────────────────
$msgSuryono = "Assalamu'alaikum warahmatullahi wabarakatuh 🙏\n\n"
    . "Mohon maaf yang sebesar-besarnya atas keteledoran kami 🙏\n\n"
    . "Foto iklan Anda untuk listing *Layanan Berbagai Jenis Asuransi* "
    . "sempat terhapus secara otomatis akibat bug pada sistem kami.\n\n"
    . "✅ Iklan teks Anda sudah *tersimpan dan aktif* di marketplace kami.\n"
    . "❌ Namun foto belum terlampir.\n\n"
    . "Mohon berkenan kirim ulang foto iklan asuransi tersebut ke grup, "
    . "insya Allah sistem akan langsung otomatis melampirkannya ke iklan Anda. 🙏\n\n"
    . "Jazakallahu khairan 🙏";

echo "Sending to Suryono (93793663627297@lid)...\n";
$r2 = sendLidDM($gatewayUrl, $gatewayToken, '93793663627297@lid', $msgSuryono);
echo "Result: " . json_encode($r2) . "\n\n";

echo "DONE\n";
