<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 1. Collect all media URLs from listings #45, #46 AND the video message #940
$listing44 = \App\Models\Listing::find(44);
$listing45 = \App\Models\Listing::find(45);
$listing46 = \App\Models\Listing::find(46);
$videoMsg = \App\Models\Message::find(940);

$allMedia = [];

// Add video from message 940
if ($videoMsg && $videoMsg->media_url) {
    $allMedia[] = $videoMsg->media_url;
    echo "Video from msg 940: {$videoMsg->media_url}\n";
}

// Add images from listing 45
if ($listing45 && is_array($listing45->media_urls)) {
    foreach ($listing45->media_urls as $url) {
        $allMedia[] = $url;
        echo "Image from listing 45: {$url}\n";
    }
}

// Add images from listing 46
if ($listing46 && is_array($listing46->media_urls)) {
    foreach ($listing46->media_urls as $url) {
        $allMedia[] = $url;
        echo "Image from listing 46: {$url}\n";
    }
}

echo "\nTotal media to merge: " . count($allMedia) . "\n";

// 2. Update listing #44 with all media
$listing44->media_urls = $allMedia;
$listing44->save();
echo "Updated listing #44 media_urls: " . json_encode($listing44->media_urls) . "\n";

// 3. Delete duplicate listings
if ($listing45) {
    $listing45->delete();
    echo "Deleted listing #45\n";
}
if ($listing46) {
    $listing46->delete();
    echo "Deleted listing #46\n";
}

// 4. Update messages 941-944 to reference listing #44 instead
\App\Models\Message::whereIn('id', [941, 942, 943, 944])
    ->update(['message_category' => 'ad_companion', 'is_ad' => true]);
echo "Updated messages 941-944 to ad_companion\n";

// 5. Verify
$listing44->refresh();
echo "\n=== Final listing #44 ===\n";
echo "Title: {$listing44->title}\n";
echo "Status: {$listing44->status}\n";
echo "media_urls: " . json_encode($listing44->media_urls) . "\n";

// Also check all Dwi Janto listings
echo "\n=== All Dwi Janto listings after fix ===\n";
$listings = \App\Models\Listing::whereHas('message', fn($q) => $q->where('sender_number', '628119880220'))
    ->orderBy('id', 'desc')
    ->get(['id', 'title', 'status', 'media_urls', 'message_id']);
foreach ($listings as $l) {
    echo "Listing #{$l->id}: {$l->title} status:{$l->status} media:" . json_encode($l->media_urls) . "\n";
}
