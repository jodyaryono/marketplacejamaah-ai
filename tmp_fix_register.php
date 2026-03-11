<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '113335764791545';

// 1. Mark contact as registered
$updated = DB::table('contacts')
    ->where('phone_number', $phone)
    ->update(['is_registered' => true, 'updated_at' => now()]);
echo "Marked contact as registered: $updated rows\n";

// 2. Reset their pending_onboarding messages so they get reprocessed
$msgIds = DB::table('messages')
    ->where('sender_number', $phone)
    ->where('message_category', 'pending_onboarding')
    ->pluck('id')
    ->toArray();
echo "Messages to reprocess: " . count($msgIds) . "\n";

DB::table('messages')
    ->whereIn('id', $msgIds)
    ->update(['is_processed' => false, 'processed_at' => null, 'message_category' => null]);

// 3. Dispatch jobs for each message
foreach ($msgIds as $id) {
    \App\Jobs\ProcessMessageJob::dispatch($id)->onQueue('agents');
    echo "  Dispatched ProcessMessageJob for message $id\n";
}

echo "\nDone! Messages will be reprocessed by queue workers.\n";
