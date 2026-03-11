<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Fix all listings where description != original raw_body
// (i.e. Gemini produced a shortened/summarized version)
$listings = \App\Models\Listing::with('message')->whereNotNull('message_id')->get();
$fixed = 0;

foreach ($listings as $listing) {
    $msg = $listing->message;
    if (!$msg || empty($msg->raw_body)) continue;
    
    $raw = $msg->raw_body;
    $desc = $listing->description ?? '';
    
    // If raw is longer and description appears to be a shortened/summarized version
    if (strlen($raw) > strlen($desc) + 50) {
        $listing->description = $raw;
        $listing->save();
        echo "Fixed listing #{$listing->id} '{$listing->title}': desc ".strlen($desc)." chars -> raw ".strlen($raw)." chars\n";
        $fixed++;
    }
}

echo "\nTotal fixed: $fixed listings\n";
