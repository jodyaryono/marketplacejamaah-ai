<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Show IAT groups
$groups = App\Models\WhatsappGroup::all();
foreach ($groups as $g) {
    echo "id={$g->id} group_id={$g->group_id} name={$g->name}\n";
}

// Show Arfan contact + any pending_group_approval status
$c = App\Models\Contact::where('phone_number', '62817799993')->first();
echo "\nArfan: status={$c->onboarding_status}\n";
