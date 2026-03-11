<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check Dwi Janto's new messages
echo "=== Dwi Janto group messages (latest 20) ===\n";
$msgs = \App\Models\Message::where('sender_number', '628119880220')
    ->whereNotNull('whatsapp_group_id')
    ->orderBy('id', 'desc')
    ->limit(20)
    ->get(['id', 'message_type', 'media_url', 'is_ad', 'message_category', 'is_processed', 'created_at', 'raw_body']);
foreach ($msgs as $m) {
    $body = substr($m->raw_body ?? '', 0, 60);
    echo "ID:{$m->id} type:{$m->message_type} ad:{$m->is_ad} cat:{$m->message_category} proc:{$m->is_processed} media:" . ($m->media_url ? basename($m->media_url) : 'no') . " [{$m->created_at}] {$body}\n";
}

// Check listing #44
echo "\n=== Listing #44 ===\n";
$listing = \App\Models\Listing::find(44);
if ($listing) {
    echo "Title: {$listing->title}\n";
    echo "Status: {$listing->status}\n";
    echo "media_urls: " . json_encode($listing->media_urls) . "\n";
    echo "message_id: {$listing->message_id}\n";
}

// Check for any NEW listings from Dwi Janto
echo "\n=== All Dwi Janto listings ===\n";
$listings = \App\Models\Listing::whereHas('message', fn($q) => $q->where('sender_number', '628119880220'))
    ->orderBy('id', 'desc')
    ->get(['id', 'title', 'status', 'media_urls', 'message_id', 'created_at']);
foreach ($listings as $l) {
    echo "Listing #{$l->id}: {$l->title} status:{$l->status} media:" . json_encode($l->media_urls) . " msg:{$l->message_id} [{$l->created_at}]\n";
}

// Check queue
echo "\n=== Queue status ===\n";
$pending = \DB::table('jobs')->count();
$failed = \DB::table('failed_jobs')->count();
echo "Pending: {$pending}, Failed: {$failed}\n";

// Check failed jobs
$failedJobs = \DB::table('failed_jobs')->orderBy('id', 'desc')->limit(5)->get(['id', 'exception', 'failed_at']);
foreach ($failedJobs as $fj) {
    echo "Failed #{$fj->id} [{$fj->failed_at}]: " . substr($fj->exception, 0, 150) . "\n";
}
