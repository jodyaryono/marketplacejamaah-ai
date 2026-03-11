<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '62817799993';
$contact = App\Models\Contact::where('phone_number', $phone)->first();
if ($contact) {
    echo "Found: id={$contact->id} name={$contact->name} status={$contact->onboarding_status} registered={$contact->is_registered} blocked={$contact->is_blocked}\n";
} else {
    echo "Not found, creating...\n";
    $contact = App\Models\Contact::create(['phone_number' => $phone, 'name' => 'Arfan Mentemas']);
}

// Reset and resend
$contact->update(['is_blocked' => false, 'onboarding_status' => 'pending', 'is_registered' => false]);

$wa = app(App\Services\WhacenterService::class);
$intro = "Assalamu'alaikum wa rahmatullahi wa barakatuh! \xF0\x9F\x99\x8F\n\nPerkenalkan, saya *Admin Marketplace Jamaah* - komunitas jual beli sesama Muslim yang amanah dan berkah. Senang banget ada anggota baru bergabung, semoga jadi ladang berkah ya \xF0\x9F\x98\x8A\n\nBiar bisa aktif di grup, boleh kenalan dulu nih? Nama siapa, tinggal di mana, dan tertarik jualan, belanja, atau dua-duanya? \xF0\x9F\x98\x8A";
$wa->sendMessage($phone, $intro);
echo "Onboarding DM sent to {$phone}\n";
