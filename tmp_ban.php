<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '628138547063';
$contact = App\Models\Contact::where('phone_number', $phone)->first();
if (!$contact) {
    echo "Contact not found for {$phone}\n";
    exit(1);
}
$contact->update(['is_blocked' => true, 'onboarding_status' => null]);
echo "BANNED: {$contact->name} ({$phone})\n";
