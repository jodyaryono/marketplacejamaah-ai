<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Keep Listing #17 (text+image combined), delete #16 and #18
$keep = \App\Models\Listing::find(17);
echo "Keeping Listing #17: {$keep->title}\n";
echo "  media: ".json_encode($keep->media_urls)."\n";
echo "  desc: {$keep->description}\n\n";

// Delete duplicate listings
foreach ([16, 18] as $id) {
    $l = \App\Models\Listing::find($id);
    if ($l && $l->contact_id == 24) {
        echo "Deleting Listing #{$id}: {$l->title}\n";
        $l->delete();
    }
}

echo "\n--- Remaining listings for Suryono ---\n";
$remaining = \App\Models\Listing::where('contact_id', 24)->get();
foreach ($remaining as $l) {
    echo "Listing #{$l->id} | {$l->title} | status={$l->status} | media=".json_encode($l->media_urls)."\n";
}
echo "Total: {$remaining->count()} listing(s)\n";
