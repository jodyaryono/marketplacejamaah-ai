#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id

echo "=== Supervisor config ==="
cat /etc/supervisor/conf.d/marketplacejamaah-queue.conf 2>/dev/null || cat /etc/supervisor/conf.d/marketplacejamaah*.conf 2>/dev/null | head -20

echo ""
echo "=== Jobs table ==="
php artisan tinker --execute="
\$jobs = \DB::table('jobs')->take(5)->get(['id','queue','attempts','created_at']);
dump(\$jobs->toArray());
"

echo ""
echo "=== Try processing queue manually ==="
php artisan queue:work --queue=agents,default --once 2>&1 | head -20
