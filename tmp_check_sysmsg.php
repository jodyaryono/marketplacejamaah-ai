<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$rows = \App\Models\SystemMessage::select('key')->get()->pluck('key')->toArray();
echo implode("\n", $rows) . "\n";
echo "---oneliner---\n";
$r = \App\Models\SystemMessage::where('key','like','%oneliner%')->orWhere('key','like','%one_liner%')->first();
echo $r ? $r->key . ': ' . substr($r->body,0,100) : 'NOT FOUND';
