<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check ALL messages after 22:10 WIB from Dwi Janto in group
$msgs = \App\Models\Message::where('sender_number', '628119880220')
    ->whereNotNull('whatsapp_group_id')
    ->where('created_at', '>=', '2026-03-11 22:10:00')
    ->orderBy('id', 'desc')
    ->get(['id', 'message_type', 'media_url', 'is_ad', 'message_category', 'is_processed', 'created_at', 'raw_body']);
echo "Dwi Janto group msgs after 22:10: " . $msgs->count() . "\n";
foreach ($msgs as $m) {
    echo "ID:{$m->id} type:{$m->message_type} media:" . ($m->media_url ? $m->media_url : 'no') . " proc:{$m->is_processed} [{$m->created_at}]\n";
}

// Also check absolute latest messages
echo "\n=== Absolute latest 10 messages ===\n";
$latest = \App\Models\Message::orderBy('id', 'desc')->limit(10)
    ->get(['id', 'sender_number', 'sender_name', 'message_type', 'media_url', 'is_ad', 'message_category', 'is_processed', 'whatsapp_group_id', 'created_at']);
foreach ($latest as $m) {
    $grp = $m->whatsapp_group_id ? "G" : "DM";
    echo "ID:{$m->id} [{$m->created_at}] {$m->sender_name}({$m->sender_number}) {$grp} type:{$m->message_type} media:" . ($m->media_url ? 'YES' : 'no') . " proc:{$m->is_processed}\n";
}

// Check uploads dir for recent files
echo "\n=== Latest uploads ===\n";
$uploads = glob('/var/www/integrasi-wa.jodyaryono.id/uploads/*');
usort($uploads, fn($a, $b) => filemtime($b) - filemtime($a));
foreach (array_slice($uploads, 0, 5) as $f) {
    echo basename($f) . " " . date('Y-m-d H:i:s', filemtime($f)) . " " . filesize($f) . "\n";
}

// Check laravel log for recent webhook
echo "\n=== Recent webhook log ===\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recent = array_slice($lines, -30);
    foreach ($recent as $line) {
        if (stripos($line, 'webhook') !== false || stripos($line, '628119880220') !== false) {
            echo trim($line) . "\n";
        }
    }
}
