<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$unprocessed = DB::table('messages')->where('is_processed', false)->where('direction', 'in')->count();
echo "Unprocessed messages (is_processed=false, direction=in): $unprocessed\n";

$jobs = DB::table('jobs')->count();
echo "Queue jobs pending: $jobs\n";

$failed = DB::table('failed_jobs')->count();
echo "Failed jobs: $failed\n";

$recent = DB::table('messages')->where('direction', 'in')->orderByDesc('created_at')->limit(5)->get(['id', 'created_at', 'is_processed']);
echo "\nMost recent 5 inbound messages:\n";
foreach($recent as $r) {
    echo "  id={$r->id} created_at={$r->created_at} is_processed={$r->is_processed}\n";
}

$recentUnprocessed = DB::table('messages')->where('is_processed', false)->where('direction', 'in')->orderByDesc('created_at')->limit(5)->get(['id', 'created_at']);
echo "\nMost recent 5 unprocessed:\n";
foreach($recentUnprocessed as $r) {
    echo "  id={$r->id} created_at={$r->created_at}\n";
}
if (count($recentUnprocessed) === 0) echo "  (none)\n";
