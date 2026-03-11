#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute='
$wa = app(App\Services\WhacenterService::class);
$result = $wa->getGroupParticipants("6285719195627-1540340459@g.us");
$participants = $result["participants"] ?? $result["data"] ?? $result;
echo json_encode($participants, JSON_PRETTY_PRINT);
'
