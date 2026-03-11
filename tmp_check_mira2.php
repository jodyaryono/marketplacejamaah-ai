<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check message 117 state
$msg = \App\Models\Message::find(117);
echo "Msg #117: type={$msg->message_type} | proc=".($msg->is_processed?'Y':'N')." | is_ad=".($msg->is_ad===null?'NULL':($msg->is_ad?'Y':'N'))." | cat={$msg->message_category} | media=".($msg->media_url ?: 'none')."\n";

// Check listings for Mirakusuma (contact 25)
$listings = \App\Models\Listing::where('contact_id', 25)->get();
echo "\n--- Listings for Mirakusuma (contact 25) ---\n";
foreach($listings as $l) {
    echo "Listing #{$l->id} | {$l->title} | status={$l->status} | msg_id={$l->message_id}\n";
    echo "  media: ".json_encode($l->media_urls)."\n";
    echo "  desc: ".substr($l->description ?? '',0,200)."\n\n";
}

// Also check latest listings
echo "--- Latest 5 listings ---\n";
$latest = \App\Models\Listing::orderBy('id','desc')->take(5)->get(['id','title','status','contact_id','message_id','media_urls']);
foreach($latest as $l) {
    echo "#{$l->id} | contact={$l->contact_id} | msg={$l->message_id} | {$l->title} | {$l->status} | media=".count($l->media_urls ?? [])."\n";
}
