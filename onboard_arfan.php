<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$phone = '62817799993';
$contact = App\Models\Contact::firstOrCreate(
    ['phone_number' => $phone],
    ['name' => 'Arfan Mentemas']
);
$wa = app(App\Services\WhacenterService::class);
$intro = "Assalamu'alaikum wa rahmatullahi wa barakatuh!\n\nPerkenalkan, saya *Admin Marketplace Jamaah* - komunitas jual beli sesama Muslim yang amanah dan berkah. Senang banget ada anggota baru bergabung, semoga jadi ladang berkah ya\n\nBiar bisa aktif di grup, boleh kenalan dulu nih? Nama siapa, tinggal di mana, dan tertarik jualan, belanja, atau dua-duanya?";
$wa->sendMessage($phone, $intro);
$contact->update(['onboarding_status' => 'pending']);
echo "DONE\n";
