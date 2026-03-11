<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '6281385477063';
$count = DB::table('messages')->where('sender_number', $phone)->count();
echo "Raw count: {$count}\n";
$del = DB::table('messages')->where('sender_number', $phone)->delete();
echo "Deleted: {$del}\n";
