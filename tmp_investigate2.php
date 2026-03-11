<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get Dwi Janto's image/video messages with full raw_payload
$msgs = \App\Models\Message::whereIn('id', [901, 903, 904, 906, 907, 899, 898])
    ->orderBy('id')
    ->get();

foreach ($msgs as $m) {
    echo "=== MSG ID {$m->id} (type: {$m->message_type}, cat: {$m->message_category}) ===\n";
    $payload = is_array($m->raw_payload) ? $m->raw_payload : json_decode($m->raw_payload, true);
    echo "Keys: " . implode(', ', array_keys($payload ?? [])) . "\n";
    if (isset($payload['media_url'])) echo "media_url: {$payload['media_url']}\n";
    if (isset($payload['mediaUrl'])) echo "mediaUrl: {$payload['mediaUrl']}\n";
    if (isset($payload['media'])) echo "media: " . substr($payload['media'], 0, 100) . "...\n";
    if (isset($payload['file'])) echo "file: {$payload['file']}\n";
    if (isset($payload['image'])) echo "image: " . substr(json_encode($payload['image']), 0, 200) . "\n";
    echo "message: " . ($payload['message'] ?? 'null') . "\n";
    echo "type: " . ($payload['type'] ?? 'null') . "\n";
    echo "\n";
}

// Check listing 44 details
$listing = \App\Models\Listing::find(44);
echo "=== LISTING 44 ===\n";
echo "Title: {$listing->title}\n";
echo "Description: {$listing->description}\n";
echo "Media: " . json_encode($listing->media_urls) . "\n";
echo "Contact: {$listing->contact_id}\n";
echo "Message: {$listing->message_id}\n";

// Check contact
$contact = $listing->contact;
echo "Contact name: " . ($contact->name ?? 'null') . "\n";
echo "Contact phone: " . ($contact->phone_number ?? 'null') . "\n";
