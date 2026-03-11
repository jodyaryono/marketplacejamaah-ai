<?php
/**
 * Retroactively handle Albany's unprocessed message #228
 * - Delete the Threads.com URL from the group
 * - Send Albany a DM explaining the removal
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$message = App\Models\Message::find(228);
if (!$message) {
    echo "ERROR: Message 228 not found\n";
    exit(1);
}

echo "Message: {$message->id} | sender: {$message->sender_number} | type: {$message->message_type}\n";
echo "Body: " . substr($message->raw_body ?? '', 0, 80) . "\n";

$wa = app(App\Services\WhacenterService::class);

// 1. Delete from group
$payload    = $message->raw_payload ?? [];
$messageKey = $payload['_key'] ?? null;
$groupJid   = $payload['group_id'] ?? $payload['from_group'] ?? null;

echo "\nDeleting from group...\n";
try {
    if ($messageKey) {
        $result = $wa->deleteMessage($messageKey);
        echo "deleteMessage result: " . json_encode($result) . "\n";
    } elseif ($message->message_id && $groupJid) {
        $result = $wa->deleteGroupMessage($groupJid, $message->message_id, $message->sender_number);
        echo "deleteGroupMessage result: " . json_encode($result) . "\n";
    } else {
        echo "WARNING: No message key or group JID found\n";
    }
} catch (Throwable $e) {
    echo "ERROR deleting: " . $e->getMessage() . "\n";
}

// 2. Send DM
$name      = $message->sender_name ?? 'Albany';
$groupName = $message->group?->group_name ?? 'Marketplace Jamaah';
$dmText = "Halo *{$name}*! 👋\n\n"
    . "Pesan kamu di grup *{$groupName}* sudah kami hapus karena tidak bisa diproses sebagai iklan. 🙏\n\n"
    . "Jika kamu ingin menjual/menawarkan produk atau jasa, silakan kirim ulang dengan format lengkap:\n\n"
    . "📌 *Nama produk/jasa*\n"
    . "💰 *Harga* (contoh: Rp 50.000 atau 50rb)\n"
    . "📝 *Deskripsi singkat* (kondisi, ukuran, dll)\n"
    . "📍 *Lokasi* (kota/kecamatan)\n"
    . "📞 *Kontak* (kalau beda dari nomor ini)\n\n"
    . "_Contoh:_\n"
    . "Jual *Kue Bolu Kukus* 🎂\n"
    . "Harga: Rp 25.000/kotak\n"
    . "Rasa coklat, vanilla, pandan\n"
    . "Lokasi: Depok, bisa COD\n\n"
    . "Jika pesan kamu bukan iklan, gunakan prefix *@info*, *@diskusi*, dll. agar tidak dihapus.\n\n"
    . "Terima kasih 🙏";

echo "\nSending DM to {$message->sender_number}...\n";
try {
    $result = $wa->sendMessage($message->sender_number, $dmText);
    echo "sendMessage result: " . json_encode($result) . "\n";
} catch (Throwable $e) {
    echo "ERROR sending DM: " . $e->getMessage() . "\n";
}

// 3. Update message category
$message->update(['message_category' => 'extraction_failed']);
echo "\nMessage category updated to extraction_failed\n";
echo "DONE\n";
