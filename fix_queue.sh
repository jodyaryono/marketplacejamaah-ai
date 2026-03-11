#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id

echo "=== PostgreSQL status ==="
systemctl status postgresql | head -5

echo ""
echo "=== DB connection test ==="
php artisan tinker --execute="try { \DB::connection()->getPdo(); echo 'DB OK'; } catch (\Exception \$e) { echo 'DB ERROR: ' . \$e->getMessage(); }"

echo ""
echo "=== Queue pending jobs ==="
php artisan tinker --execute="echo 'agents queue: ' . \DB::table('jobs')->where('queue','agents')->count() . PHP_EOL; echo 'default queue: ' . \DB::table('jobs')->where('queue','default')->count() . PHP_EOL;"

echo ""
echo "=== Restart queue workers ==="
php artisan queue:restart
supervisorctl restart marketplacejamaah-queue:*

echo ""
echo "=== Test ProcessMessageJob for message 13 ==="
php artisan tinker --execute="
\App\Jobs\ProcessMessageJob::dispatch(13)->onQueue('agents');
echo 'Dispatched!';
"
