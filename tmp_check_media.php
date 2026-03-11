<?php
define('APP_PATH', '/var/www/marketplacejamaah-ai.jodyaryono.id');
chdir(APP_PATH);
require APP_PATH . '/vendor/autoload.php';
$app = require APP_PATH . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Backfill: For messages that have media_url in raw_payload but NULL in media_url column
echo "=== BACKFILL messages.media_url FROM raw_payload ===\n";
$fixed = 0;
$msgs = App\Models\Message::whereNull('media_url')
    ->whereIn('message_type', ['image', 'video', 'document', 'audio', 'sticker'])
    ->get();
foreach ($msgs as $m) {
    $payload = $m->raw_payload;
    if (is_array($payload)) {
        $url = $payload['media_url'] ?? $payload['url'] ?? null;
        if ($url) {
            $m->update(['media_url' => $url]);
            echo "  Fixed msg#{$m->id}: {$url}\n";
            $fixed++;
        }
    }
}
echo "Fixed {$fixed} messages from raw_payload\n";

// Now backfill listings that are missing media_urls but have messages with media
echo "\n=== BACKFILL listings.media_urls FROM messages ===\n";
$fixedListings = 0;
$listings = App\Models\Listing::whereNull('media_urls')
    ->orWhereRaw("media_urls::text = '[]'")
    ->orWhereRaw("media_urls::text = 'null'")
    ->get();
foreach ($listings as $l) {
    $msg = App\Models\Message::find($l->message_id);
    if ($msg && $msg->media_url) {
        $l->update(['media_urls' => [$msg->media_url]]);
        echo "  Fixed listing#{$l->id} '{$l->title}': {$msg->media_url}\n";
        $fixedListings++;
    }
}
echo "Fixed {$fixedListings} listings\n";

echo "\n=== FINAL STATUS ===\n";
$total = App\Models\Listing::count();
$withMedia = App\Models\Listing::whereNotNull('media_urls')
    ->whereRaw("media_urls::text != '[]'")
    ->whereRaw("media_urls::text != 'null'")
    ->count();
echo "Total listings: {$total}, With media: {$withMedia}, Without: " . ($total - $withMedia) . "\n";
