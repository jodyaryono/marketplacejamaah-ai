<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Merge Suryo (id=13, phone=6281213338417) into syn🥰 (id=20, phone=93793663627297)
// syn🥰 already has the listing and is registered — keep that one.
// Suryo (id=13) has old messages from an old phone number — reassign to syn's number.

$oldPhone = '6281213338417';
$newPhone = '93793663627297';

echo "=== Before merge ===\n";
$old = DB::table('contacts')->where('phone_number', $oldPhone)->first(['id','name','phone_number','message_count','is_registered']);
$new = DB::table('contacts')->where('phone_number', $newPhone)->first(['id','name','phone_number','message_count','is_registered']);
echo "OLD: id={$old->id} name={$old->name} phone={$old->phone_number} msgs={$old->message_count} reg={$old->is_registered}\n";
echo "NEW: id={$new->id} name={$new->name} phone={$new->phone_number} msgs={$new->message_count} reg={$new->is_registered}\n";

$oldMsgCount = DB::table('messages')->where('sender_number', $oldPhone)->count();
echo "\nMessages with old phone: $oldMsgCount\n";

// Step 1: Reassign messages from old number to new number
$updated = DB::table('messages')->where('sender_number', $oldPhone)->update(['sender_number' => $newPhone]);
echo "Reassigned $updated messages from $oldPhone → $newPhone\n";

// Step 2: Delete old contact (id=13)
DB::table('contacts')->where('id', $old->id)->delete();
echo "Deleted old contact id={$old->id} ($oldPhone)\n";

// Step 3: Update message_count on syn's contact
$totalMsgs = DB::table('messages')->where('sender_number', $newPhone)->count();
DB::table('contacts')->where('phone_number', $newPhone)->update(['message_count' => $totalMsgs]);
echo "Updated syn🥰 message_count to $totalMsgs\n";

echo "\n=== After merge ===\n";
$merged = DB::table('contacts')->where('phone_number', $newPhone)->first(['id','name','phone_number','message_count','is_registered']);
echo "syn🥰: id={$merged->id} name={$merged->name} phone={$merged->phone_number} msgs={$merged->message_count} reg={$merged->is_registered}\n";

echo "\nDONE\n";
