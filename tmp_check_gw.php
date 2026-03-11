<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check if ANY group messages arrived after 22:00
$msgs = \App\Models\Message::whereNotNull('whatsapp_group_id')
    ->where('created_at', '>=', '2026-03-11 22:00:00')
    ->orderBy('id', 'desc')
    ->limit(10)
    ->get(['id', 'sender_name', 'sender_number', 'whatsapp_group_id', 'created_at', 'message_type', 'media_url']);
echo "=== Group msgs after 22:00 WIB: {$msgs->count()} ===\n";
foreach ($msgs as $m) {
    echo "ID:{$m->id} [{$m->created_at}] {$m->sender_name}({$m->sender_number}) grp:{$m->whatsapp_group_id} type:{$m->message_type} media:" . ($m->media_url ? 'YES' : 'no') . "\n";
}

// Check gateway log - maybe it started logging after restart
echo "\n=== Gateway log tail ===\n";
$log = @file_get_contents('/tmp/wa_gateway.log');
if ($log) {
    $lines = explode("\n", trim($log));
    echo "Total lines: " . count($lines) . "\n";
    foreach (array_slice($lines, -20) as $l) echo $l . "\n";
} else {
    echo "No log or empty\n";
}

// Check gateway process stdout
echo "\n=== Gateway process ===\n";
echo shell_exec('pgrep -fla "index.js" 2>&1');

// Check if the gateway node process is writing to any other log
echo "\n=== Check /proc for gateway fd ===\n";
$pid = trim(shell_exec('pgrep -f "node.*index.js" 2>/dev/null'));
if ($pid) {
    echo "PID: $pid\n";
    echo shell_exec("ls -la /proc/$pid/fd/1 /proc/$pid/fd/2 2>&1");
}

// Check any recent gateway errors or messages
echo "\n=== pm2 or systemd status ===\n";
echo shell_exec('systemctl status wa-gateway 2>&1 | head -20');
echo shell_exec('pm2 list 2>&1 | head -10');

// Check webhook route is accessible
echo "\n=== Test webhook route ===\n";
$result = shell_exec('curl -s -o /dev/null -w "%{http_code}" -X POST https://marketplacejamaah-ai.jodyaryono.id/api/webhook/whacenter -H "Content-Type: application/json" -d "{}" 2>&1');
echo "Webhook status code: $result\n";
