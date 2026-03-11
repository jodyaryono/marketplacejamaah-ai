<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// 1. Fix Suryono contact - complete onboarding, fix name
$contact = \App\Models\Contact::where('phone_number', '6281213338417')->first();
echo "Before: id={$contact->id} name={$contact->name} onboarding={$contact->onboarding_status} registered=".($contact->is_registered?'Y':'N')."\n";

$contact->update([
    'name' => 'Suryono Al Aqsha',
    'onboarding_status' => 'completed',
    'member_role' => 'seller',
    'sell_products' => 'Asuransi Generali Syariah',
]);

echo "After: name={$contact->name} onboarding={$contact->onboarding_status} role={$contact->member_role}\n\n";

// 2. Reset messages #113 (image) and #116 (text) for reprocessing
$msgIds = [113, 116];
foreach ($msgIds as $id) {
    $msg = \App\Models\Message::find($id);
    if ($msg) {
        echo "Resetting msg #{$id}: type={$msg->message_type} | was: proc=".($msg->is_processed?'Y':'N')." is_ad=".($msg->is_ad===null?'NULL':($msg->is_ad?'Y':'N'))." cat={$msg->message_category}\n";
        $msg->update([
            'is_processed' => false,
            'is_ad' => null,
            'message_category' => null,
            'processed_at' => null,
        ]);
        // Dispatch to queue
        \App\Jobs\ProcessMessageJob::dispatch($msg->id);
        echo "  → Dispatched to queue\n";
    }
}

echo "\nDone! Messages dispatched for reprocessing.\n";
