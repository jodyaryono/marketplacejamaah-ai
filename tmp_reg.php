<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '62817799993';
$contact = App\Models\Contact::where('phone_number', $phone)->first();
$contact->update([
    'name' => 'Arfan Mentemas',
    'is_registered' => true,
    'onboarding_status' => 'completed',
    'is_blocked' => false,
]);

$wa = app(App\Services\WhacenterService::class);
$msg = "Alhamdulillah, *Arfan Mentemas* sudah terdaftar sebagai anggota Marketplace Jamaah! \xF0\x9F\x8E\x89\n\nBarakallahu fiik, semoga jadi ladang berkah buat kita semua ya \xF0\x9F\x99\x8F\n\nSilahkan aktif di grup, kalau ada pertanyaan langsung tanya aja ya!";
$wa->sendMessage($phone, $msg);
echo "APPROVED & DM sent: {$contact->name} ({$phone})\n";
