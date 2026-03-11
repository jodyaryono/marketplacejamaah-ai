<?php
// Check Dwi Janto's latest messages and listing status
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// All messages from Dwi Janto (628119880220)
$messages = \App\Models\Message::where('sender_number', '628119880220')
    ->orderBy('id', 'desc')
    ->limit(20)
    ->get(['id', 'message_type', 'raw_body', 'media_url', 'is_ad', 'ad_confidence', 'message_category', 'is_processed', 'whatsapp_group_id', 'created_at']);

echo "=== MESSAGES FROM DWI JANTO ===\n";
foreach ($messages as $m) {
    $body = $m->raw_body ? substr($m->raw_body, 0, 60) : '(null)';
    echo "ID:{$m->id} type:{$m->message_type} ad:{$m->is_ad} cat:{$m->message_category} processed:{$m->is_processed} media:" . ($m->media_url ? 'YES' : 'no') . " [{$m->created_at}] body:{$body}\n";
    if ($m->media_url) echo "   media_url: {$m->media_url}\n";
}

// Listing #44
echo "\n=== LISTING #44 ===\n";
$listing = \App\Models\Listing::find(44);
if ($listing) {
    echo "ID:{$listing->id} title:{$listing->title}\n";
    echo "status:{$listing->status} message_id:{$listing->message_id}\n";
    echo "media_urls: " . json_encode($listing->media_urls) . "\n";
    echo "source_date: {$listing->source_date}\n";
}

// Any other listings from same contact
echo "\n=== ALL LISTINGS FROM CONTACT 64 (Dwi Janto) ===\n";
$listings = \App\Models\Listing::where('contact_id', 64)->orderBy('id', 'desc')->get(['id', 'title', 'status', 'media_urls', 'message_id', 'source_date']);
foreach ($listings as $l) {
    $mediaCount = is_array($l->media_urls) ? count($l->media_urls) : 0;
    echo "ID:{$l->id} title:{$l->title} status:{$l->status} media_count:{$mediaCount} msg_id:{$l->message_id} [{$l->source_date}]\n";
    if ($mediaCount > 0) echo "   media_urls: " . json_encode($l->media_urls) . "\n";
}
