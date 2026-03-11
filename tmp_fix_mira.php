<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// 1. Register Mirakusuma
$contact = \App\Models\Contact::where('phone_number', '6287843201618')->first();
echo "Before: name={$contact->name} registered=".($contact->is_registered?'Y':'N')." onboarding={$contact->onboarding_status}\n";

$contact->update([
    'is_registered' => true,
    'onboarding_status' => 'completed',
    'member_role' => 'seller',
    'sell_products' => 'Putu Mayang, Kue Tradisional',
]);
echo "After: registered=Y onboarding=completed role=seller\n\n";

// 2. Reset message #117 for reprocessing
$msg = \App\Models\Message::find(117);
if ($msg) {
    echo "Resetting msg #117: type={$msg->message_type} | was: proc=".($msg->is_processed?'Y':'N')." is_ad=".($msg->is_ad===null?'NULL':($msg->is_ad?'Y':'N'))." cat={$msg->message_category}\n";
    echo "  raw_body: ".substr($msg->raw_body ?? '', 0, 120)."\n";
    echo "  media_url: {$msg->media_url}\n";
    $msg->update([
        'is_processed' => false,
        'is_ad' => null,
        'message_category' => null,
        'processed_at' => null,
    ]);
    \App\Jobs\ProcessMessageJob::dispatch($msg->id);
    echo "  -> Dispatched to queue\n";
}

echo "\nDone!\n";
