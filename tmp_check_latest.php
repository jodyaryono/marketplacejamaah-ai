<?php
// Check latest messages from ALL senders + queue
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Latest 15 messages overall
echo "=== LATEST 15 MESSAGES ===\n";
$messages = \App\Models\Message::orderBy('id', 'desc')->limit(15)
    ->get(['id', 'sender_number', 'sender_name', 'message_type', 'raw_body', 'media_url', 'is_ad', 'message_category', 'is_processed', 'whatsapp_group_id', 'created_at']);
foreach ($messages as $m) {
    $body = $m->raw_body ? substr($m->raw_body, 0, 50) : '(null)';
    $name = $m->sender_name ?? 'unknown';
    $grp = $m->whatsapp_group_id ? "G:{$m->whatsapp_group_id}" : 'DM';
    echo "ID:{$m->id} [{$m->created_at}] {$name}({$m->sender_number}) {$grp} type:{$m->message_type} ad:{$m->is_ad} cat:{$m->message_category} proc:{$m->is_processed} media:" . ($m->media_url ? 'YES' : 'no') . "\n";
    if ($m->media_url) echo "   media_url: {$m->media_url}\n";
    echo "   body: {$body}\n";
}

// Check queue jobs
echo "\n=== PENDING QUEUE JOBS ===\n";
$jobs = \Illuminate\Support\Facades\DB::table('jobs')->orderBy('id', 'desc')->limit(10)->get(['id', 'queue', 'payload', 'created_at']);
echo "Total pending: " . \Illuminate\Support\Facades\DB::table('jobs')->count() . "\n";
foreach ($jobs as $j) {
    $payload = json_decode($j->payload, true);
    $cmd = $payload['displayName'] ?? 'unknown';
    echo "Job ID:{$j->id} queue:{$j->queue} cmd:{$cmd} created:{$j->created_at}\n";
}

// Check failed jobs
echo "\n=== RECENT FAILED JOBS ===\n";
$failed = \Illuminate\Support\Facades\DB::table('failed_jobs')->orderBy('id', 'desc')->limit(5)->get(['id', 'queue', 'payload', 'failed_at']);
echo "Total failed: " . \Illuminate\Support\Facades\DB::table('failed_jobs')->count() . "\n";
foreach ($failed as $f) {
    $payload = json_decode($f->payload, true);
    $cmd = $payload['displayName'] ?? 'unknown';
    echo "Failed ID:{$f->id} queue:{$f->queue} cmd:{$cmd} failed:{$f->failed_at}\n";
}
