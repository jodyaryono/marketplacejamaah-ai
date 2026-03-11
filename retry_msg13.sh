#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id

echo "=== Dispatch ProcessMessageJob for msg 13 ==="
php artisan tinker --execute="
\App\Jobs\ProcessMessageJob::dispatch(13)->onQueue('agents');
echo 'Dispatched to agents queue';
"

sleep 20

echo ""
echo "=== Queue status ==="
php artisan tinker --execute="
echo 'agents: ' . \DB::table('jobs')->where('queue','agents')->count() . PHP_EOL;
echo 'default: ' . \DB::table('jobs')->where('queue','default')->count() . PHP_EOL;
"

echo ""
echo "=== Message 13 result ==="
php artisan tinker --execute="
\$m = \App\Models\Message::find(13);
echo 'processed: ' . (\$m->is_processed ? 'yes' : 'no') . PHP_EOL;
echo 'category: ' . \$m->category . PHP_EOL;
echo 'ai_summary: ' . \$m->ai_summary . PHP_EOL;
\$logs = \App\Models\AgentLog::where('message_id',13)->orderBy('id')->get();
foreach (\$logs as \$l) echo \$l->agent_name . ' => ' . \$l->status . ' | ' . \$l->error . PHP_EOL;
"

echo ""
echo "=== Queue log ==="
tail -20 storage/logs/queue.log
