<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$listing = \App\Models\Listing::find(20);
if (!$listing) { echo "Listing #20 NOT FOUND\n"; exit; }

echo "Listing #20:\n";
echo "  title: {$listing->title}\n";
echo "  status: {$listing->status}\n";
echo "  message_id: {$listing->message_id}\n";
echo "  contact_id: {$listing->contact_id}\n";
echo "  media_urls: ".json_encode($listing->media_urls, JSON_PRETTY_PRINT)."\n";
echo "  description: ".substr($listing->description ?? '', 0, 300)."\n\n";

// Check the source message
$msg = \App\Models\Message::find($listing->message_id);
if ($msg) {
    echo "Source Message #{$msg->id}:\n";
    echo "  type: {$msg->message_type}\n";
    echo "  media_url: {$msg->media_url}\n";
    echo "  media_filename: {$msg->media_filename}\n";
    echo "  sender: {$msg->sender_number} ({$msg->sender_name})\n";
    echo "  raw_body: ".substr($msg->raw_body ?? '', 0, 200)."\n";
    $payload = $msg->raw_payload;
    if ($payload) {
        echo "  payload media_url: ".($payload['media_url'] ?? 'null')."\n";
        echo "  payload type: ".($payload['type'] ?? 'null')."\n";
    }
}

// Check all messages near this listing for related media
echo "\n--- Related messages from same sender ---\n";
if ($msg) {
    $related = \App\Models\Message::where('sender_number', $msg->sender_number)
        ->where('whatsapp_group_id', $msg->whatsapp_group_id)
        ->orderBy('sent_at', 'desc')
        ->take(10)
        ->get(['id','message_type','raw_body','is_ad','media_url','sent_at','message_category']);
    foreach ($related as $r) {
        echo "  #{$r->id} | {$r->message_type} | is_ad=".($r->is_ad===null?'NULL':($r->is_ad?'Y':'N'))." | cat={$r->message_category} | media=".($r->media_url ?: 'none')." | ".substr($r->raw_body ?? '',0,60)." | {$r->sent_at}\n";
    }
}
