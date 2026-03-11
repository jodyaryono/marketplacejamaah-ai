<?php
chdir('/var/www/marketplacejamaah-ai.jodyaryono.id');
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Messages #149, #150, #151 are the 3 image messages that were wrongly attached to listing #22
$imageMessageIds = [149, 150, 151];

// 1. Clear listing #22's media_urls — it's a text-only ad, images belong to separate listings
$listing22 = App\Models\Listing::find(22);
if ($listing22) {
    $listing22->update(['media_urls' => []]);
    echo "Listing #22 media_urls cleared" . PHP_EOL;
}

// 2. Reset the 3 image messages so they get reprocessed as independent listings
foreach ($imageMessageIds as $id) {
    $m = App\Models\Message::find($id);
    if ($m) {
        $m->update([
            'is_processed'     => false,
            'processed_at'     => null,
            'is_ad'            => null,
            'ad_confidence'    => null,
            'message_category' => null,
        ]);
        App\Jobs\ProcessMessageJob::dispatch($m->id);
        echo "Reset and requeued message #{$id} (media_url=" . ($m->media_url ? 'yes' : 'NO') . ")" . PHP_EOL;
    } else {
        echo "Message #{$id} not found" . PHP_EOL;
    }
}

echo 'Done' . PHP_EOL;
