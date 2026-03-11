<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '113335764791545';

// Reset the wrongly-deleted image/video/text messages to be reprocessed
$imgIds = DB::table('messages')
    ->where('sender_number', $phone)
    ->where('message_category', 'non_ad_deleted')
    ->whereIn('message_type', ['image', 'video', 'document', 'audio'])
    ->pluck('id')
    ->toArray();

echo "Image/video messages to reprocess: " . count($imgIds) . "\n";

DB::table('messages')
    ->whereIn('id', $imgIds)
    ->update(['is_processed' => false, 'processed_at' => null, 'message_category' => null]);

foreach ($imgIds as $id) {
    \App\Jobs\ProcessMessageJob::dispatch($id)->onQueue('agents');
    echo "  Dispatched message $id\n";
}

echo "\nDone!\n";
