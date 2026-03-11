<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check raw_payload to find actual JID format
$m1 = DB::table('messages')->where('sender_number', '113335764791545')->orderByDesc('id')->first(['sender_number','raw_payload']);
echo "=== Hamba Alloh raw_payload ===\n";
if ($m1) {
    $p = json_decode($m1->raw_payload, true);
    echo "Keys: " . implode(', ', array_keys($p ?? [])) . "\n";
    // Look for JID/from/sender
    foreach (['from', 'sender', 'jid', 'participant', 'author'] as $k) {
        if (isset($p[$k])) echo "  $k = " . $p[$k] . "\n";
    }
}

$m2 = DB::table('messages')->where('sender_number', '93793663627297')->orderByDesc('id')->first(['sender_number','raw_payload']);
echo "\n=== Suryono raw_payload ===\n";
if ($m2) {
    $p = json_decode($m2->raw_payload, true);
    echo "Keys: " . implode(', ', array_keys($p ?? [])) . "\n";
    foreach (['from', 'sender', 'jid', 'participant', 'author'] as $k) {
        if (isset($p[$k])) echo "  $k = " . $p[$k] . "\n";
    }
}
