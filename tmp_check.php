<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Show distinct sender_numbers containing 8138547
$rows = DB::table('messages')->where('sender_number', 'LIKE', '%8138547%')->select('sender_number')->distinct()->get();
echo "Distinct senders matching 8138547:\n";
foreach ($rows as $r) { echo "  [{$r->sender_number}]\n"; }
$total = DB::table('messages')->count();
echo "Total messages in table: {$total}\n";
