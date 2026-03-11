<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$listing = DB::table('listings')->where('id', 15)->first();
echo "Listing id=15:\n";
echo "  title: {$listing->title}\n";
echo "  status: {$listing->status}\n";
echo "  price: {$listing->price}\n";
echo "  price_label: {$listing->price_label}\n";
echo "  description: " . substr($listing->description ?? '', 0, 200) . "\n";
echo "  media_urls: {$listing->media_urls}\n";
echo "  location: {$listing->location}\n";
