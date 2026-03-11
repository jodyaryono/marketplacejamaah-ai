<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Show table columns
$cols = DB::select("SHOW COLUMNS FROM messages");
echo "Columns:\n";
foreach ($cols as $c) { echo "  {$c->Field} ({$c->Type})\n"; }

// Check with deleted_at included
$count = DB::statement("SELECT COUNT(*) c FROM messages WHERE sender_number LIKE '%8138547%'");
$rows = DB::select("SELECT id, sender_number, deleted_at FROM messages WHERE sender_number LIKE '%8138547%' LIMIT 5");
echo "\nDirect SQL results: " . count($rows) . "\n";
foreach ($rows as $r) { echo "  id={$r->id} sender={$r->sender_number}\n"; }
