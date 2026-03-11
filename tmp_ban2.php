<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '6281385477063';
$deleted = App\Models\Message::where('sender_number', $phone)->delete();
echo "Deleted messages: {$deleted}\n";

$contact = App\Models\Contact::where('phone_number', $phone)->first();
if ($contact) {
    $contact->update(['is_blocked' => true, 'onboarding_status' => null]);
    echo "BANNED: {$contact->name} ({$phone})\n";
} else {
    echo "Contact not found\n";
}
