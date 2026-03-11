<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '6281385477063';

// Get message IDs first
$ids = DB::table('messages')
    ->where('recipient_number', $phone)
    ->orWhere('sender_number', $phone)
    ->pluck('id')
    ->toArray();
echo "Message IDs: " . implode(',', $ids) . "\n";

if (!empty($ids)) {
    // Delete agent_logs first (FK child)
    $al = DB::table('agent_logs')->whereIn('message_id', $ids)->delete();
    echo "agent_logs deleted: {$al}\n";
    // Now delete messages
    $m = DB::table('messages')->whereIn('id', $ids)->delete();
    echo "messages deleted: {$m}\n";
} else {
    // Check why pluck returns nothing
    $raw = DB::select("SELECT id, recipient_number FROM messages WHERE recipient_number = ?", [$phone]);
    echo "Raw SQL count: " . count($raw) . "\n";
}
