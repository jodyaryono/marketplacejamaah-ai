<?php
// Check ALL latest messages including Dwi Janto's new images
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Latest 25 messages
echo "=== LATEST 25 MESSAGES ===\n";
$messages = \App\Models\Message::orderBy('id', 'desc')->limit(25)
    ->get(['id', 'sender_number', 'sender_name', 'message_type', 'raw_body', 'media_url', 'is_ad', 'ad_confidence', 'message_category', 'is_processed', 'whatsapp_group_id', 'created_at']);
foreach ($messages as $m) {
    $body = $m->raw_body ? substr($m->raw_body, 0, 60) : '(null)';
    $name = $m->sender_name ?? 'unknown';
    $grp = $m->whatsapp_group_id ? "G:{$m->whatsapp_group_id}" : 'DM';
    echo "ID:{$m->id} [{$m->created_at}] {$name}({$m->sender_number}) {$grp} type:{$m->message_type} ad:{$m->is_ad} conf:{$m->ad_confidence} cat:{$m->message_category} proc:{$m->is_processed}\n";
    if ($m->media_url) echo "   media_url: {$m->media_url}\n";
    echo "   body: {$body}\n";
}

// Dwi Janto messages after 22:00
echo "\n=== DWI JANTO MESSAGES AFTER 22:00 ===\n";
$dwi = \App\Models\Message::where('sender_number', '628119880220')
    ->where('created_at', '>=', '2026-03-11 15:00:00')
    ->orderBy('id', 'asc')
    ->get(['id', 'message_type', 'raw_body', 'media_url', 'is_ad', 'ad_confidence', 'message_category', 'is_processed', 'whatsapp_group_id', 'created_at', 'raw_payload']);
foreach ($dwi as $m) {
    $body = $m->raw_body ? substr($m->raw_body, 0, 80) : '(null)';
    $grp = $m->whatsapp_group_id ? "G:{$m->whatsapp_group_id}" : 'DM';
    echo "ID:{$m->id} [{$m->created_at}] {$grp} type:{$m->message_type} ad:{$m->is_ad} conf:{$m->ad_confidence} cat:{$m->message_category} proc:{$m->is_processed}\n";
    if ($m->media_url) echo "   media_url: {$m->media_url}\n";
    echo "   body: {$body}\n";
    // Check raw_payload for media_url
    $pay = $m->raw_payload ?? [];
    if (isset($pay['media_url'])) echo "   payload_media_url: {$pay['media_url']}\n";
}

// All listings from Dwi Janto
echo "\n=== LISTINGS FROM DWI JANTO ===\n";
$listings = \App\Models\Listing::where('contact_id', 64)->orderBy('id', 'desc')->get();
foreach ($listings as $l) {
    echo "ID:{$l->id} title:{$l->title} status:{$l->status} msg_id:{$l->message_id} [{$l->source_date}]\n";
    echo "   media_urls: " . json_encode($l->media_urls) . "\n";
}

// Pending queue
echo "\n=== PENDING QUEUE ===\n";
echo "Jobs: " . \Illuminate\Support\Facades\DB::table('jobs')->count() . "\n";
echo "Failed: " . \Illuminate\Support\Facades\DB::table('failed_jobs')->count() . "\n";
