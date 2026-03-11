<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check all columns of messages table
$cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='messages' ORDER BY ordinal_position");
echo "Messages columns:\n";
foreach ($cols as $c) echo "  " . $c->column_name . "\n";

// Check recent outgoing DMs to see what number format was used successfully before
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

// Check messages from our target numbers
$msgs = DB::table('messages')
    ->whereIn('sender_number', ['113335764791545', '93793663627297'])
    ->orderByDesc('id')
    ->limit(6)
    ->get();
echo "\nMessages from targets:\n";
foreach ($msgs as $m) {
    $cols2 = array_keys((array)$m);
    echo "  columns: " . implode(', ', $cols2) . "\n";
    break;
}
foreach ($msgs as $m) {
    echo "  id={$m->id} sender_number={$m->sender_number} type={$m->message_type}\n";
}
