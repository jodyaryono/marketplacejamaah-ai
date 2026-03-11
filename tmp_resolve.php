<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gatewayUrl = rtrim(config('services.wa_gateway.url'), '/');
$gatewayToken = config('services.wa_gateway.token', '');

// Resolve LID contacts to real phone
$lids = ['93793663627297@lid', '189893439447082@lid'];

foreach ($lids as $lid) {
    echo "Resolving $lid...\n";
    $response = \Illuminate\Support\Facades\Http::timeout(30)
        ->withHeaders(['Authorization' => 'Bearer ' . $gatewayToken])
        ->post($gatewayUrl . '/resolve-contact', ['jid' => $lid]);
    echo "  Status: " . $response->status() . "\n";
    echo "  Body: " . $response->body() . "\n\n";
}
