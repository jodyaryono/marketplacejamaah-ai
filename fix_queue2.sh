#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id

echo "=== Fix supervisor to listen agents AND default queues ==="
sed -i 's/--queue=agents/--queue=agents,default/' /etc/supervisor/conf.d/marketplacejamaah-queue.conf

echo "=== Reload supervisor ==="
supervisorctl reread
supervisorctl update
supervisorctl restart marketplacejamaah-queue:*

echo ""
echo "=== Check agents queue jobs ==="
php artisan tinker --execute="
\$agents = \DB::table('jobs')->where('queue','agents')->get(['id','queue','payload']);
echo 'agents queue: ' . \$agents->count() . PHP_EOL;
foreach (\$agents as \$j) { echo 'Job ' . \$j->id . ': ' . substr(\$j->payload, 0, 100) . PHP_EOL; }
"

echo ""
echo "=== Wait and process ==="
sleep 10

echo "=== Queue status after ==="
php artisan tinker --execute="
echo 'agents: ' . \DB::table('jobs')->where('queue','agents')->count() . PHP_EOL;
echo 'default: ' . \DB::table('jobs')->where('queue','default')->count() . PHP_EOL;
"

echo ""
echo "=== Message 13 status ==="
php artisan tinker --execute="
\$m = \App\Models\Message::find(13);
echo 'processed: ' . (\$m->is_processed ? 'yes' : 'no') . PHP_EOL;
echo 'category: ' . \$m->category . PHP_EOL;
\$logs = \App\Models\AgentLog::where('message_id',13)->get();
foreach (\$logs as \$l) echo \$l->agent_name . ' => ' . \$l->status . PHP_EOL;
"
