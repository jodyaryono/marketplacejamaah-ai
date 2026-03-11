<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check Suryono's listing
echo "=== Suryono (93793663627297) listings ===\n";
$contact = DB::table('contacts')->where('phone_number', '93793663627297')->first();
if ($contact) {
    echo "Contact: id={$contact->id} name={$contact->name} registered={$contact->is_registered}\n";
    $listings = DB::table('listings')->where('contact_id', $contact->id)->get();
    foreach ($listings as $l) {
        echo "  Listing id={$l->id} title={$l->title} status={$l->status}\n";
        echo "  media_urls: {$l->media_urls}\n";
    }
} else {
    echo "  Contact NOT FOUND\n";
}

echo "\n=== Hamba Alloh listing media_urls ===\n";
$l = DB::table('listings')->where('id', 15)->first(['id','title','media_urls','price','price_label']);
echo "  id={$l->id} title={$l->title}\n";
echo "  media_urls: {$l->media_urls}\n";
echo "  price: {$l->price} / label: {$l->price_label}\n";
