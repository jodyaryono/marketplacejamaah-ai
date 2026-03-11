<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check ALL messages near 09:50 from group 3
$msgs = \App\Models\Message::where('whatsapp_group_id', 3)
    ->where('sent_at', '>=', '2026-03-08 09:48:00')
    ->where('sent_at', '<=', '2026-03-08 09:55:00')
    ->orderBy('sent_at','asc')
    ->get(['id','message_type','raw_body','is_ad','media_url','sent_at','sender_number','sender_name','message_category']);

echo "--- Group messages 09:48–09:55 ---\n";
foreach($msgs as $m) {
    echo "#{$m->id} | {$m->sender_name} ({$m->sender_number}) | {$m->message_type} | is_ad=".($m->is_ad===null?'NULL':($m->is_ad?'Y':'N'))." | cat={$m->message_category} | media=".($m->media_url ? 'YES' : 'none')." | ".substr($m->raw_body ?? '',0,60)." | {$m->sent_at}\n";
}

// Also check gateway log for any dropped media around that time
echo "\n--- Check gateway.log around 09:50 ---\n";
$log = file_get_contents('/var/www/integrasi-wa.jodyaryono.id/gateway.log');
$lines = explode("\n", $log);
foreach ($lines as $line) {
    if (str_contains($line, '09:50') || str_contains($line, '09:51') || str_contains($line, '09:49')) {
        echo $line . "\n";
    }
}
