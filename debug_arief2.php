<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// All agent logs for message #792
$logs = App\Models\AgentLog::where('message_id', 792)->orderBy('id')->get();
echo "=== All Agent Logs for msg #792 ===\n";
foreach ($logs as $l) {
    echo "log#{$l->id} | {$l->agent_name} | {$l->status} | " . json_encode($l->output_payload) . " | err=" . ($l->error ?? '-') . " | {$l->duration_ms}ms\n";
}

// Check MemberOnboardingAgent logs around same time
echo "\n=== MemberOnboardingAgent recent logs ===\n";
$onboard = App\Models\AgentLog::where('agent_name', 'MemberOnboardingAgent')
    ->orderBy('id', 'desc')
    ->take(10)
    ->get();
foreach ($onboard as $l) {
    echo "log#{$l->id} | msg#{$l->message_id} | {$l->status} | " . json_encode($l->output_payload) . " | err=" . ($l->error ?? '-') . "\n";
}

// Check BotQueryAgent logs
echo "\n=== BotQueryAgent recent logs ===\n";
$bq = App\Models\AgentLog::where('agent_name', 'BotQueryAgent')
    ->orderBy('id', 'desc')
    ->take(10)
    ->get();
foreach ($bq as $l) {
    echo "log#{$l->id} | msg#{$l->message_id} | {$l->status} | " . json_encode($l->output_payload) . " | err=" . ($l->error ?? '-') . "\n";
}

// Also check: what message did the bot send back to Arief?
echo "\n=== Messages TO Arief (bot replies) ===\n";
$botMsgs = App\Models\Message::where('sender_number', '6281317647379')
    ->whereNull('whatsapp_group_id')
    ->orderBy('id', 'desc')
    ->take(20)
    ->get();
foreach ($botMsgs as $m) {
    if (str_contains($m->raw_body ?? '', 'Arief') || str_contains($m->raw_body ?? '', '6281517800900') || str_contains($m->raw_body ?? '', 'arief')) {
        echo "#{$m->id} | to={$m->sender_number} | " . mb_substr($m->raw_body ?? '', 0, 150) . "\n";
    }
}

// Check if BotQueryAgent actually sent a reply - look at WhacenterService logs
echo "\n=== Laravel log tail (Arief related) ===\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $relevant = array_filter($lines, fn($l) => str_contains(strtolower($l), 'arief') || str_contains($l, '6281517800900') || str_contains($l, 'botquery'));
    foreach (array_slice($relevant, -10) as $line) {
        echo trim($line) . "\n";
    }
}
