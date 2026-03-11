<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$l = \App\Models\Listing::whereNotNull('media_urls')
    ->whereRaw("media_urls::text != '[]'")
    ->first();
if ($l) echo "Listing {$l->id}: " . json_encode($l->media_urls) . "\n";
else echo "No listings with media found\n";
