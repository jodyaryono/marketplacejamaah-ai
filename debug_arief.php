<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Message;
use App\Models\Contact;
use App\Models\AgentLog;

// Check Arief's contact
$arief = Contact::where('phone_number', '6281517800900')->first();
echo "=== Arief Contact ===\n";
echo json_encode($arief?->toArray(), JSON_PRETTY_PRINT) . "\n\n";

// Check Arief's messages
$msgs = Message::where('sender_number', '6281517800900')
    ->orWhere('sender_number', '113292815134928')
    ->orderBy('id', 'desc')
    ->take(15)
    ->get();
echo "=== Arief Messages ===\n";
foreach ($msgs as $m) {
    echo "#{$m->id} | {$m->sender_number} | {$m->sender_name} | " 
        . ($m->whatsapp_group_id ? "GROUP({$m->whatsapp_group_id})" : "DM")
        . " | " . mb_substr($m->raw_body ?? '', 0, 100)
        . " | proc={$m->is_processed}"
        . " | {$m->created_at}\n";
}

// Check agent logs for Arief's messages
echo "\n=== Agent Logs for Arief ===\n";
$messageIds = $msgs->pluck('id')->toArray();
$logs = AgentLog::whereIn('message_id', $messageIds)
    ->orderBy('id', 'desc')
    ->take(20)
    ->get();
foreach ($logs as $l) {
    echo "msg#{$l->message_id} | {$l->agent_name} | {$l->status} | " 
        . json_encode($l->output_payload) 
        . " | err=" . ($l->error ?? '-')
        . "\n";
}

// Also check failed jobs
echo "\n=== Recent Failed Jobs ===\n";
$failed = DB::table('failed_jobs')->orderBy('id', 'desc')->take(5)->get(['id', 'exception', 'failed_at']);
foreach ($failed as $f) {
    echo "#{$f->id} | {$f->failed_at} | " . mb_substr($f->exception, 0, 200) . "\n";
}
