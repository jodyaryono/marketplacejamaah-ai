<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$wa = app(App\Services\WhacenterService::class);

// The service normalizes numbers, but since @lid includes @, numberToJid() returns as-is
// Test: send using the WhacenterService's gatewayPost method directly won't work
// So let's check how sendMessage handles the number

// First, note the gateway URL
$gatewayUrl = rtrim(config('services.wa_gateway.url'), '/');
$gatewayToken = config('services.wa_gateway.token', '');
echo "Gateway URL: $gatewayUrl\n";

// Test with correct path (URL already has /api included, so service appends /send = /api/send)
$response = \Illuminate\Support\Facades\Http::timeout(30)
    ->withHeaders(['Authorization' => 'Bearer ' . $gatewayToken])
    ->post($gatewayUrl . '/send', [
        'number' => '113335764791545@lid',
        'message' => 'test pesan dari server',
    ]);

echo "Status: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";
