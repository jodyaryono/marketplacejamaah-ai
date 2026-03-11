<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$wa = app(App\Services\WhacenterService::class);

// Get group participants to find ibu Ana's real phone
$groupId = '6285719195627-1540340459@g.us';
echo "=== Getting group participants ===\n";
$participants = $wa->getGroupParticipants($groupId);
echo json_encode($participants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
