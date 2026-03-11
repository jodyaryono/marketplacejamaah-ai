<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$msgs = DB::table('messages')
    ->whereIn('id', [80, 85, 86, 87, 88])
    ->get();

foreach ($msgs as $m) {
    echo "id={$m->id} type={$m->message_type} media_url=" . ($m->media_url ?? 'NULL') . "\n";
    // Check all columns for any URL-like values
    $arr = (array)$m;
    foreach ($arr as $k => $v) {
        if ($v && (str_contains((string)$v, 'http') || str_contains((string)$v, 'upload'))) {
            echo "  [$k] => $v\n";
        }
    }
}
