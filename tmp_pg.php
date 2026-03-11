<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = DB::select("SELECT id, sender_number FROM messages WHERE sender_number LIKE '%8138547%' LIMIT 10");
echo "Found: " . count($rows) . "\n";
foreach ($rows as $r) { echo "  id={$r->id} sender={$r->sender_number}\n"; }

// Also try outgoing messages (direction=out, recipient)
$rows2 = DB::select("SELECT id, direction, recipient_number, sender_number FROM messages WHERE recipient_number LIKE '%8138547%' LIMIT 5");
echo "Recipient matches: " . count($rows2) . "\n";
foreach ($rows2 as $r) { echo "  id={$r->id} dir={$r->direction} rec={$r->recipient_number}\n"; }
