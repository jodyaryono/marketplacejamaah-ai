<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find Arfan
$contacts = App\Models\Contact::where('phone_number', 'LIKE', '%817799%')
    ->orWhere('name', 'LIKE', '%arfan%')
    ->get();
echo "Contacts:\n";
foreach ($contacts as $c) {
    echo "  id={$c->id} phone={$c->phone_number} name={$c->name} status={$c->onboarding_status} registered={$c->is_registered} blocked={$c->is_blocked}\n";
}

// Messages
$msgs = App\Models\Message::where('sender_number', 'LIKE', '%817799%')
    ->orWhere('recipient_number', 'LIKE', '%817799%')
    ->orderBy('id', 'desc')->take(10)->get();
echo "\nMessages (last 10):\n";
foreach ($msgs as $m) {
    echo "  id={$m->id} dir={$m->direction} sender={$m->sender_number} rec={$m->recipient_number} body=" . substr($m->raw_body ?? '', 0, 60) . "\n";
}
