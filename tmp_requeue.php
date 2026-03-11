<?php
chdir('/var/www/marketplacejamaah-ai.jodyaryono.id');
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$msgs = App\Models\Message::where('message_category', 'pending_onboarding')->get();
echo 'Found: ' . $msgs->count() . PHP_EOL;

foreach ($msgs as $m) {
    $m->update([
        'is_processed' => false,
        'processed_at' => null,
        'message_category' => null,
    ]);
    App\Jobs\ProcessMessageJob::dispatch($m->id);
    echo 'Requeued #' . $m->id . ' sender=' . $m->sender_number . PHP_EOL;
}

echo 'Done' . PHP_EOL;
