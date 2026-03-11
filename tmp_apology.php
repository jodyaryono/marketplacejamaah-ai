<?php
// Send apology to Dwi Janto for deleted images
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$wa = app(\App\Services\WhacenterService::class);

$phone = '628119880220'; // Dwi Janto
$text = "Halo *Dwi Janto*! 🙏\n\n"
    . "Kami dari tim *Marketplace Jamaah* mau minta maaf yang sebesar-besarnya 🙇‍♂️\n\n"
    . "Kemarin foto dan video produk *Baso dan Tahu Baso* yang kakak kirim ke grup, "
    . "sayangnya terhapus otomatis oleh sistem kami karena ada kesalahan teknis di bot. "
    . "Sistem salah menganggap foto terpisah bukan bagian dari iklan kakak. 😞\n\n"
    . "Kami sudah *perbaiki sistemnya* hari ini supaya kejadian seperti ini tidak terulang lagi.\n\n"
    . "Mohon maaf banget ya Kak 🙏 Bisa tolong kirim ulang foto-fotonya ke grup? "
    . "Kali ini dijamin aman dan akan langsung masuk ke iklan kakak. ✅\n\n"
    . "Sekali lagi maaf atas ketidaknyamanannya. Terima kasih sudah bersabar! 🤲";

$result = $wa->sendMessage($phone, $text);
echo "Sent to {$phone}: " . json_encode($result) . "\n";
