<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check messages #113 and #116 current state
foreach ([113, 116] as $id) {
    $m = \App\Models\Message::find($id);
    echo "Msg #{$id}: type={$m->message_type} | proc=".($m->is_processed?'Y':'N')." | is_ad=".($m->is_ad===null?'NULL':($m->is_ad?'Y':'N'))." | cat={$m->message_category} | media=".($m->media_url ?: 'none')."\n";
}

// Check all listings for contact 24
echo "\n--- All Listings for Suryono (contact 24) ---\n";
$listings = \App\Models\Listing::where('contact_id', 24)->get();
foreach ($listings as $l) {
    echo "Listing #{$l->id} | {$l->title} | status={$l->status} | msg_id={$l->message_id}\n";
    echo "  media_urls: ".json_encode($l->media_urls)."\n";
    echo "  desc: ".substr($l->description ?? '', 0, 200)."\n\n";
}

// Check listing 17 and 18 specifically
echo "--- Listing #17 ---\n";
$l17 = \App\Models\Listing::find(17);
if ($l17) echo "contact_id={$l17->contact_id} | msg_id={$l17->message_id} | {$l17->title} | media=".json_encode($l17->media_urls)."\n";
else echo "NOT FOUND\n";

echo "--- Listing #18 ---\n";
$l18 = \App\Models\Listing::find(18);
if ($l18) echo "contact_id={$l18->contact_id} | msg_id={$l18->message_id} | {$l18->title} | media=".json_encode($l18->media_urls)."\n";
else echo "NOT FOUND\n";
