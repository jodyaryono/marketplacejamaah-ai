<?php
chdir('/var/www/marketplacejamaah-ai.jodyaryono.id');
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach ([149, 150, 151] as $id) {
    $m = App\Models\Message::find($id);
    echo "=== Message #{$id} ===" . PHP_EOL;
    echo "type={$m->message_type} media_url=" . ($m->media_url ?? 'NULL') . PHP_EOL;
    echo "raw_body={$m->raw_body}" . PHP_EOL;
    // Show relevant payload keys
    $p = $m->raw_payload ?? [];
    $keys = ['media_url', 'url', 'image', 'media', 'file_url', 'download_url', 'mimetype', 'has_media'];
    foreach ($keys as $k) {
        if (isset($p[$k])) echo "  payload.$k=" . json_encode($p[$k]) . PHP_EOL;
    }
    echo PHP_EOL;
}
