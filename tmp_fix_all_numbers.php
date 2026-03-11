<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Fix remaining LID → real phone
$fixes = [
    '93793663627297'  => '6281213338417',  // syn🥰 / Suryo
    '189893439447082' => '6287780772199',  // Abdul MRBJ
];

foreach ($fixes as $lid => $realPhone) {
    echo "=== Fixing $lid → $realPhone ===\n";

    $lidContact = DB::table('contacts')->where('phone_number', $lid)->first();
    $existing = DB::table('contacts')->where('phone_number', $realPhone)->first();

    if (!$lidContact) {
        echo "  No LID contact found, skipping\n\n";
        continue;
    }

    if ($existing) {
        // Merge: reassign messages + listings, delete LID contact
        $msgUpdated = DB::table('messages')->where('sender_number', $lid)->update(['sender_number' => $realPhone]);
        echo "  Reassigned $msgUpdated messages\n";
        $listUpdated = DB::table('listings')->where('contact_id', $lidContact->id)->update(['contact_id' => $existing->id]);
        echo "  Moved $listUpdated listings to contact id={$existing->id}\n";
        // Merge registration/name into existing if better
        if ($lidContact->is_registered && !$existing->is_registered) {
            DB::table('contacts')->where('id', $existing->id)->update(['is_registered' => true]);
            echo "  Copied is_registered=true\n";
        }
        if ($lidContact->name && (!$existing->name || $existing->name === $existing->phone_number)) {
            DB::table('contacts')->where('id', $existing->id)->update(['name' => $lidContact->name]);
            echo "  Copied name={$lidContact->name}\n";
        }
        DB::table('contacts')->where('id', $lidContact->id)->delete();
        echo "  Deleted LID contact id={$lidContact->id}\n";
        $totalMsgs = DB::table('messages')->where('sender_number', $realPhone)->count();
        DB::table('contacts')->where('phone_number', $realPhone)->update(['message_count' => $totalMsgs]);
        echo "  Updated message_count=$totalMsgs\n";
    } else {
        // Just rename
        DB::table('contacts')->where('phone_number', $lid)->update(['phone_number' => $realPhone]);
        echo "  Renamed phone\n";
        $msgUpdated = DB::table('messages')->where('sender_number', $lid)->update(['sender_number' => $realPhone]);
        echo "  Reassigned $msgUpdated messages\n";
    }
    echo "\n";
}

// Also fix recipient_number in outgoing messages
echo "=== Fixing outgoing DM recipient_numbers ===\n";
$allFixes = ['113335764791545' => '6287876695683', '93793663627297' => '6281213338417', '189893439447082' => '6287780772199'];
foreach ($allFixes as $lid => $real) {
    $u = DB::table('messages')->where('recipient_number', $lid)->update(['recipient_number' => $real]);
    if ($u > 0) echo "  Fixed $u outgoing messages for $lid → $real\n";
}

// Verify final state
echo "\n=== Final contacts ===\n";
$contacts = DB::table('contacts')->orderBy('id')->get(['id','phone_number','name','message_count','is_registered']);
foreach ($contacts as $c) {
    $prefix = str_starts_with($c->phone_number, '62') ? '✅' : '❌';
    echo "  $prefix id={$c->id} phone={$c->phone_number} name={$c->name} msgs={$c->message_count} reg={$c->is_registered}\n";
}
echo "\nDONE\n";
