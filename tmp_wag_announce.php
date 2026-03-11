<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get active groups
$groups = \App\Models\WhatsappGroup::where('is_active', true)->pluck('group_name');
echo 'Active groups: ' . json_encode($groups) . "\n";

// Send announcement to each group
$whacenter = app(\App\Services\WhacenterService::class);

$message = "🆕 *FITUR BARU: Edit Iklan via Chat WhatsApp!*\n\n"
    . "Sekarang kamu bisa *mengedit iklan langsung dari chat WhatsApp* tanpa perlu buka website! 🎉\n\n"
    . "📝 *Cara pakai:*\n"
    . "1️⃣ Chat pribadi bot ini\n"
    . "2️⃣ Ketik: *edit #nomor_iklan*\n"
    . "   Contoh: _edit #42_\n"
    . "3️⃣ Bot akan tampilkan data iklan kamu\n"
    . "4️⃣ Kirim perubahan pakai bahasa bebas!\n"
    . "   Contoh: _harga jadi 50rb, tambah deskripsi ready stock_\n\n"
    . "✅ AI akan otomatis update field yang tepat\n"
    . "🔒 Hanya pemilik iklan yang bisa edit (aman!)\n\n"
    . "📹 *Bonus:* Upload video produk (MP4/MOV/WEBM) sudah didukung!\n\n"
    . 'Ketik *bantuan* di chat pribadi bot untuk lihat semua fitur 🙏';

foreach ($groups as $groupName) {
    echo "Sending to: {$groupName}... ";
    $result = $whacenter->sendGroupMessage($groupName, $message);
    echo json_encode($result) . "\n";
    sleep(1);
}
echo "Done!\n";
