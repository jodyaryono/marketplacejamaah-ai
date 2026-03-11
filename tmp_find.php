<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '628138547063';
// Search with LIKE to catch any format variation
$contacts = App\Models\Contact::where('phone_number', 'LIKE', '%8138547%')->get();
echo "Contacts found:\n";
foreach ($contacts as $c) {
    echo "  id={$c->id} phone={$c->phone_number} name={$c->name}\n";
}

// Also search messages directly
$msgs = App\Models\Message::where('sender_number', 'LIKE', '%8138547%')->get();
echo "\nMessages found: " . $msgs->count() . "\n";
foreach ($msgs->take(5) as $m) {
    echo "  id={$m->id} sender={$m->sender_number} body=" . substr($m->raw_body ?? '', 0, 50) . "\n";
}
