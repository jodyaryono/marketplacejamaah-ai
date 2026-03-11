<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = DB::table('contacts')->where('phone_number', '6281213338417')->first();
echo json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
