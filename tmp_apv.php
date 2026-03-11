<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '62817799993';
$wa = app(App\Services\WhacenterService::class);

$groups = ['120363044209800195@g.us', '120363418819831746@g.us'];
foreach ($groups as $groupId) {
    try {
        $result = $wa->approveMembership($groupId, $phone);
        echo "Group {$groupId}: " . json_encode($result) . "\n";
    } catch (Exception $e) {
        echo "Group {$groupId} error: " . $e->getMessage() . "\n";
    }
}
