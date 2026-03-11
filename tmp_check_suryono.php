<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check Suryono's recent messages
$msgs = \App\Models\Message::where('contact_id', 24)
    ->orderBy('sent_at','desc')
    ->take(20)
    ->get(['id','message_type','raw_body','is_ad','status','listing_id','media_url','sent_at','whatsapp_group_id']);

foreach($msgs as $m) {
    echo $m->id.' | '.$m->message_type.' | status='.$m->status.' | is_ad='.$m->is_ad.' | listing='.$m->listing_id.' | media='.($m->media_url ? 'YES' : 'no').' | grp='.$m->whatsapp_group_id.' | '.substr($m->raw_body ?? '',0,80).' | '.$m->sent_at."\n";
}

echo "\n--- Listing #16 ---\n";
$listing = \App\Models\Listing::find(16);
if ($listing) {
    echo "title: ".$listing->title."\n";
    echo "status: ".$listing->status."\n";
    echo "media_urls: ".json_encode($listing->media_urls)."\n";
    echo "description: ".substr($listing->description ?? '', 0, 200)."\n";
}
