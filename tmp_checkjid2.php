<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check JID fields
$msgs = DB::table('messages')
    ->whereIn('sender_number', ['113335764791545', '93793663627297'])
    ->orderByDesc('id')
    ->limit(6)
    ->get(['id','sender_number','sender_jid','message_type']);

foreach ($msgs as $m) {
    echo "id={$m->id} sender_number={$m->sender_number} sender_jid={$m->sender_jid} type={$m->message_type}\n";
}

// Also check columns
$cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='messages' AND column_name LIKE '%jid%'");
echo "\nJID columns: " . implode(', ', array_column($cols, 'column_name')) . "\n";

// Check outgoing DM messages to see what number format works
$outgoing = DB::table('messages')
    ->where('direction', 'out')
    ->whereNotNull('recipient_number')
    ->orderByDesc('id')
    ->limit(5)
    ->get(['id','recipient_number','raw_body']);
echo "\nRecent outgoing DMs:\n";
foreach ($outgoing as $m) {
    echo "  id={$m->id} to={$m->recipient_number} msg=" . substr($m->raw_body, 0, 50) . "\n";
}
