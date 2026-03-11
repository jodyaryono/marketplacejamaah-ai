<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check raw_payload of the Suryo (6281213338417) messages to see actual JID
echo "=== Suryo (6281213338417) raw_payload analysis ===\n";
$msgs = DB::table('messages')->where('sender_number','6281213338417')->orderBy('id')->get(['id','message_type','raw_body','direction','raw_payload']);
foreach ($msgs as $m) {
    $p = json_decode($m->raw_payload, true) ?? [];
    $sender = $p['sender'] ?? 'N/A';
    $direction = $m->direction ?? 'N/A';
    echo "  id={$m->id} dir={$direction} type={$m->message_type} sender_jid={$sender} body=" . substr($m->raw_body ?? '', 0, 40) . "\n";
}

echo "\n=== Check messages with direction=out stored with sender_number ===\n";
$out = DB::table('messages')->where('direction','out')->whereNotNull('sender_number')->where('sender_number','!=','bot')->limit(10)->get(['id','sender_number','recipient_number','raw_body']);
foreach ($out as $m) {
    echo "  id={$m->id} sender={$m->sender_number} recipient={$m->recipient_number} body=" . substr($m->raw_body ?? '', 0, 40) . "\n";
}
