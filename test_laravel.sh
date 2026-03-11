#!/bin/bash
echo "=== Test via Laravel WhacenterService ==="
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute="
\$svc = app(\App\Services\WhacenterService::class);
\$result = \$svc->sendMessage('6285719195627', '✅ Test dari Laravel WhacenterService - integrasi berhasil!');
dump(\$result);
"
