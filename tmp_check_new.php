<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check latest messages after 22:00 UTC
$msgs = \App\Models\Message::where('created_at', '>=', '2026-03-11 22:06:00')
    ->orderBy('id', 'desc')
    ->get(['id', 'sender_number', 'sender_name', 'message_type', 'raw_body', 'media_url', 'is_ad', 'message_category', 'is_processed', 'whatsapp_group_id', 'created_at']);

echo "Messages after 22:06 UTC: " . $msgs->count() . "\n";
foreach ($msgs as $m) {
    $body = $m->raw_body ? substr($m->raw_body, 0, 60) : '(null)';
    $name = $m->sender_name ?? 'unknown';
    $grp = $m->whatsapp_group_id ? "G:{$m->whatsapp_group_id}" : 'DM';
    echo "ID:{$m->id} [{$m->created_at}] {$name}({$m->sender_number}) {$grp} type:{$m->message_type} ad:{$m->is_ad} cat:{$m->message_category} proc:{$m->is_processed}\n";
    if ($m->media_url) echo "   media_url: {$m->media_url}\n";
    echo "   body: {$body}\n";
}

// Check gateway message log
echo "\n=== GATEWAY LOG (recent) ===\n";
$gw = \Illuminate\Support\Facades\DB::connection('integrasi_wa')
    ->table('messages_log')
    ->where('created_at', '>=', now()->subMinutes(30))
    ->where('direction', 'in')
    ->orderBy('id', 'desc')
    ->limit(15)
    ->get();
foreach ($gw as $g) {
    echo "GW ID:{$g->id} from:{$g->from_number} type:{$g->media_type} [{$g->created_at}] msg:" . substr($g->message ?? '', 0, 50) . "\n";
}
