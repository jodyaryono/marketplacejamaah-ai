<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '6281385477063';

// Delete outgoing messages (bot -> user)
$del1 = DB::table('messages')->where('recipient_number', $phone)->delete();
// Delete any incoming (just in case)
$del2 = DB::table('messages')->where('sender_number', $phone)->delete();
// Delete related agent_logs
$del3 = DB::table('agent_logs')
    ->whereIn('message_id', function($q) use ($phone) {
        $q->select('id')->from('messages')
          ->where('sender_number', $phone)
          ->orWhere('recipient_number', $phone);
    })->delete();

echo "Deleted outgoing: {$del1}\n";
echo "Deleted incoming: {$del2}\n";
echo "Deleted agent_logs: {$del3}\n";
echo "DONE\n";
