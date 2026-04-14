<?php

/**
 * Backfill: normalize dirty contact_number / contacts.phone_number values
 * (0xxxx, 0813.xxxx, "medibogor", etc.) to canonical 62xxxxxxxxxx.
 *
 * Usage: php scripts/backfill_contact_phones.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contact;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

$normalize = function (?string $raw): ?string {
    if (!$raw) return null;
    $digits = preg_replace('/\D/', '', $raw);
    if (!$digits) return null;
    if (str_starts_with($digits, '0'))       $digits = '62' . substr($digits, 1);
    elseif (str_starts_with($digits, '8'))   $digits = '62' . $digits;
    elseif (!str_starts_with($digits, '62')) return null;
    return preg_match('/^62\d{8,13}$/', $digits) ? $digits : null;
};

echo "=== Listings.contact_number ===\n";
$listingsFixed = 0;
$listingsNulled = 0;
foreach (Listing::whereNotNull('contact_number')->get(['id', 'contact_number']) as $l) {
    $normalized = $normalize($l->contact_number);
    if ($normalized === $l->contact_number) continue;
    DB::table('listings')->where('id', $l->id)->update(['contact_number' => $normalized]);
    if ($normalized) { $listingsFixed++; echo "  listing #{$l->id}: '{$l->contact_number}' -> '{$normalized}'\n"; }
    else             { $listingsNulled++; echo "  listing #{$l->id}: '{$l->contact_number}' -> NULL (invalid)\n"; }
}
echo "Listings: {$listingsFixed} normalized, {$listingsNulled} nulled.\n\n";

echo "=== Contacts.phone_number ===\n";
$contactsFixed = 0;
$contactsMerged = 0;
$contactsNulled = 0;
foreach (Contact::all(['id', 'phone_number']) as $c) {
    $normalized = $normalize($c->phone_number);
    if ($normalized === $c->phone_number) continue;

    if ($normalized) {
        $existing = Contact::where('phone_number', $normalized)->where('id', '!=', $c->id)->first();
        if ($existing) {
            // Re-point listings, delete this dup
            DB::table('listings')->where('contact_id', $c->id)->update(['contact_id' => $existing->id]);
            DB::table('contacts')->where('id', $c->id)->delete();
            $contactsMerged++;
            echo "  contact #{$c->id} ('{$c->phone_number}') merged into #{$existing->id} ({$normalized})\n";
        } else {
            DB::table('contacts')->where('id', $c->id)->update(['phone_number' => $normalized]);
            $contactsFixed++;
            echo "  contact #{$c->id}: '{$c->phone_number}' -> '{$normalized}'\n";
        }
    } else {
        // Invalid junk. Can't null phone_number (unique + possibly NOT NULL).
        // Leave contact row untouched — display layer falls back to Detail button.
        $contactsNulled++;
        echo "  contact #{$c->id}: '{$c->phone_number}' is invalid, left as-is\n";
    }
}
echo "Contacts: {$contactsFixed} normalized, {$contactsMerged} merged, {$contactsNulled} invalid left as-is.\n";
