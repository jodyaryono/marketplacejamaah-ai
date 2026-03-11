<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gatewayUrl = rtrim(config('services.wa_gateway.url'), '/');
$gatewayToken = config('services.wa_gateway.token', '');
echo "Gateway: $gatewayUrl\n";

// Try sending to @lid JID with 30s timeout
$response = \Illuminate\Support\Facades\Http::timeout(30)
    ->withHeaders(['Authorization' => 'Bearer ' . $gatewayToken])
    ->post($gatewayUrl . '/api/send', [
        'number' => '113335764791545@lid',
        'message' => 'test pesan dari server',
    ]);

echo "Status: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";
