<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Fix existing LID numbers — map them to real phone numbers based on WA contact info
// From screenshot: Hamba Alloh = +62 878-7669-5683 = 6287876695683
$fixes = [
    '113335764791545' => '6287876695683', // Hamba Alloh — from WA contact screenshot
];

foreach ($fixes as $lid => $realPhone) {
    echo "Fixing $lid → $realPhone\n";

    // Fix contacts table
    $existing = DB::table('contacts')->where('phone_number', $realPhone)->first();
    $lidContact = DB::table('contacts')->where('phone_number', $lid)->first();

    if ($lidContact && $existing) {
        // Both exist — merge LID into real phone contact
        $updated = DB::table('messages')->where('sender_number', $lid)->update(['sender_number' => $realPhone]);
        echo "  Reassigned $updated messages to $realPhone\n";
        DB::table('listings')->where('contact_id', $lidContact->id)->update(['contact_id' => $existing->id]);
        echo "  Moved listings to contact id={$existing->id}\n";
        DB::table('contacts')->where('id', $lidContact->id)->delete();
        echo "  Deleted LID contact id={$lidContact->id}\n";
        $totalMsgs = DB::table('messages')->where('sender_number', $realPhone)->count();
        DB::table('contacts')->where('phone_number', $realPhone)->update(['message_count' => $totalMsgs]);
        echo "  Updated message_count=$totalMsgs\n";
    } elseif ($lidContact && !$existing) {
        // Only LID contact exists — rename phone number
        DB::table('contacts')->where('phone_number', $lid)->update(['phone_number' => $realPhone]);
        echo "  Renamed contact phone_number\n";
        $updated = DB::table('messages')->where('sender_number', $lid)->update(['sender_number' => $realPhone]);
        echo "  Reassigned $updated messages\n";
    } else {
        echo "  No LID contact found, skipping\n";
    }
}

// Also check remaining non-62 numbers
echo "\n=== Remaining non-standard numbers ===\n";
$contacts = DB::table('contacts')->get(['id','phone_number','name']);
foreach ($contacts as $c) {
    if (!str_starts_with($c->phone_number, '62')) {
        echo "  id={$c->id} phone={$c->phone_number} name={$c->name}\n";
    }
}

echo "\nDONE\n";
