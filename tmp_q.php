<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$m = \App\Models\SystemMessage::where('key','group.oneliner_warning')->first();
echo "BODY:\n" . $m->body . "\n\nPLACEHOLDERS:\n" . json_encode($m->placeholders) . "\n";
