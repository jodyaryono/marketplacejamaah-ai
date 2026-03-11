<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = \App\Models\Contact::where('phone_number', '6281413033339')->first();
if ($c) {
    echo json_encode($c->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "NOT FOUND\n";
}

echo "\n\n--- Recent messages from this number ---\n";
$msgs = \App\Models\Message::where('sender_number', '6281413033339')
    ->orderByDesc('created_at')
    ->limit(10)
    ->get(['id', 'raw_body', 'direction', 'message_category', 'is_processed', 'created_at']);
foreach ($msgs as $m) {
    echo "[{$m->created_at}] dir={$m->direction} cat={$m->message_category} body=" . substr($m->raw_body ?? '', 0, 80) . "\n";
}
