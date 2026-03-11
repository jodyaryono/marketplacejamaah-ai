<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find Raup Raup's message
$msgs = \App\Models\Message::where('sender_number', 'like', '%85219385651%')
    ->orWhere('sender_name', 'like', '%Raup%')
    ->orderBy('id', 'desc')
    ->limit(10)
    ->get(['id', 'sender_name', 'sender_number', 'whatsapp_group_id', 'message_type', 'raw_body', 'wa_message_id', 'created_at']);
echo "=== Raup messages ===\n";
foreach ($msgs as $m) {
    echo "ID:{$m->id} [{$m->created_at}] {$m->sender_name}({$m->sender_number}) grp:{$m->whatsapp_group_id} type:{$m->message_type} wa_id:{$m->wa_message_id}\n";
    echo "  body: " . substr($m->raw_body ?? '', 0, 100) . "\n";
}

// Also check latest group messages for the status mention
echo "\n=== Latest group messages ===\n";
$latest = \App\Models\Message::whereNotNull('whatsapp_group_id')
    ->orderBy('id', 'desc')
    ->limit(10)
    ->get(['id', 'sender_name', 'sender_number', 'whatsapp_group_id', 'message_type', 'raw_body', 'wa_message_id', 'created_at']);
foreach ($latest as $m) {
    echo "ID:{$m->id} [{$m->created_at}] {$m->sender_name}({$m->sender_number}) type:{$m->message_type} wa_id:{$m->wa_message_id}\n";
    echo "  body: " . substr($m->raw_body ?? '', 0, 100) . "\n";
}
