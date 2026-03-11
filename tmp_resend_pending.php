<?php
// Re-send onboarding welcome to all LID contacts with pending status
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contact;
use App\Models\SystemMessage;
use App\Services\WhacenterService;

$wa = app(WhacenterService::class);

// LID numbers are 14+ digits
$pending = Contact::where('onboarding_status', 'pending')
    ->get()
    ->filter(fn($c) => preg_match('/^\d{14,}$/', $c->phone_number));

echo 'Found ' . $pending->count() . " LID contacts with pending status\n\n";

foreach ($pending as $contact) {
    $name = $contact->name ?? $contact->phone_number;

    $intro = SystemMessage::getText(
        'onboarding.welcome',
        ['name' => $name],
        "Halo *{$name}*! 👋\n\nSelamat datang di *Marketplace Jamaah*!\n\nUntuk mulai, balas pesan ini dengan:\n*Nama Lengkap | PENJUAL / PEMBELI / KEDUANYA*\n\n_Contoh: Budi Santoso | PENJUAL_"
    );

    $result = $wa->sendMessage($contact->phone_number, $intro);
    $ok = $result['success'] ?? false;
    echo ($ok ? '✓' : '✗') . " {$contact->phone_number} ({$name}): " . json_encode($result) . "\n";
}

echo "\nDONE\n";
