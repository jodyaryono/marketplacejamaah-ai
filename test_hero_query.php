<?php
chdir('/var/www/marketplacejamaah-ai.jodyaryono.id');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
try {
    $r = \App\Models\Listing::with(['contact', 'category'])
        ->where('status', 'active')
        ->whereNotNull('media_urls')
        ->whereRaw('jsonb_array_length(media_urls) > 0')
        ->whereRaw("lower(media_urls->>0) NOT LIKE '%.mp4'")
        ->whereRaw("lower(media_urls->>0) NOT LIKE '%.mov'")
        ->latest('source_date')
        ->limit(8)
        ->get();
    echo 'OK: ' . count($r) . " results\n";
    foreach ($r as $l) {
        echo '  - id=' . $l->id . ' ' . $l->title . ' | ' . ($l->media_urls[0] ?? '') . "\n";
    }
} catch (\Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
