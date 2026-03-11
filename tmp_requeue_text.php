<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Reset text messages that were wrongly deleted
$ids = [81, 83];

DB::table('messages')
    ->whereIn('id', $ids)
    ->update([
        'is_processed' => false,
        'processed_at' => null,
        'message_category' => null,
        'is_ad' => null,
        'ad_confidence' => null,
    ]);

foreach ($ids as $id) {
    \App\Jobs\ProcessMessageJob::dispatch($id)->onQueue('agents');
    echo "Dispatched message $id\n";
}
echo "Done!\n";
