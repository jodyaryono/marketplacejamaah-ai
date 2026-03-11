<?php
chdir('/var/www/marketplacejamaah-ai.jodyaryono.id');
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$lid = '81338409447592';
$real = '6285219385651'; // +62 852-1938-5651

// 1. Update contact
$contact = App\Models\Contact::where('phone_number', $lid)->first();
if ($contact) {
    $contact->update(['phone_number' => $real]);
    echo "Contact #{$contact->id} updated: {$lid} → {$real}" . PHP_EOL;
} else {
    echo "No contact found for {$lid}" . PHP_EOL;
}

// 2. Update messages sender_number
$msgs = App\Models\Message::where('sender_number', $lid)->get();
foreach ($msgs as $m) {
    $m->update(['sender_number' => $real]);
    echo "Message #{$m->id} sender_number updated" . PHP_EOL;
}

// 3. Update listings contact_number where it stored the LID
$listings = App\Models\Listing::where('contact_number', $lid)->get();
foreach ($listings as $l) {
    $l->update(['contact_number' => $real]);
    echo "Listing #{$l->id} contact_number updated: {$lid} → {$real}" . PHP_EOL;
}

// 4. Fix listing #22 which has empty contact_number but belongs to this sender
$listing22 = App\Models\Listing::find(22);
if ($listing22 && empty($listing22->contact_number)) {
    $listing22->update(['contact_number' => $real]);
    echo "Listing #22 contact_number set to {$real}" . PHP_EOL;
}

echo 'Done' . PHP_EOL;
