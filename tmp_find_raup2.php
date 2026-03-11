<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find Raup Raup messages
$msgs = \App\Models\Message::where(function($q) {
    $q->where('sender_name', 'like', '%Raup%')
      ->orWhere('sender_number', 'like', '%8521938%');
})->orderBy('id', 'desc')->limit(10)->get();

echo "=== Raup messages ===\n";
foreach ($msgs as $m) {
    echo "ID:{$m->id} [{$m->created_at}] {$m->sender_name}({$m->sender_number}) type:{$m->message_type} grp:{$m->whatsapp_group_id}\n";
    echo "  body: " . substr($m->raw_body ?? '(empty)', 0, 100) . "\n";
    echo "  payload: " . json_encode($m->raw_payload) . "\n\n";
}

// Also check latest group messages for anything that looks like a status mention
echo "\n=== Latest 15 group messages ===\n";
$latest = \App\Models\Message::whereNotNull('whatsapp_group_id')
    ->orderBy('id', 'desc')
    ->limit(15)
    ->get();
foreach ($latest as $m) {
    echo "ID:{$m->id} [{$m->created_at}] {$m->sender_name}({$m->sender_number}) type:{$m->message_type}\n";
    echo "  body: " . substr($m->raw_body ?? '(empty)', 0, 80) . "\n";
    $p = $m->raw_payload;
    if ($p) {
        echo "  type_raw: " . ($p['type'] ?? 'N/A') . " from: " . ($p['from'] ?? 'N/A') . "\n";
    }
    echo "\n";
}
