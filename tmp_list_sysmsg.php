<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SystemMessage;

// Update onboarding.welcome
SystemMessage::where('key', 'onboarding.welcome')->update([
    'body' => "Halo *{name}*! 😊\n\nIni admin Marketplace Jamaah. Makasih ya udah gabung di grup kita! 🕌\n\nBiar kamu bisa jualan atau belanja di grup, kami perlu tau sedikit tentang kamu.\n\nCukup bales chat ini pake:\n👤 *Nama* kamu\n📍 *Kota/daerah* tinggal\n🏷️ Mau *jualan*, *belanja*, atau *dua-duanya*\n\nContoh:\n_\"Ahmad, Jakarta, mau jualan\"_\n_\"Siti, Bekasi, pembeli\"_\n\nBebas formatnya, gak perlu kaku ☺️🙏",
]);
echo "Updated: onboarding.welcome\n";

// Update onboarding.parse_retry
SystemMessage::where('key', 'onboarding.parse_retry')->update([
    'body' => "Makasih balesannya! 😊\n\nTapi maaf nih, aku belum dapet info yang dibutuhin. Bisa tolong kasih tau:\n\n1️⃣ *Nama* kamu\n2️⃣ *Kota* tinggal\n3️⃣ Mau *jualan*, *belanja*, atau *dua-duanya*\n\nContoh: _\"Siti, Bekasi, mau jual hijab\"_\n\nGak perlu format khusus kok 🙏",
]);
echo "Updated: onboarding.parse_retry\n";

echo "\nDone!\n";
