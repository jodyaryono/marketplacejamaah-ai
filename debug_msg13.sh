#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id

echo "=== Agent logs for message 13 ==="
php artisan tinker --execute="
\$logs = \App\Models\AgentLog::where('message_id', 13)->get();
foreach (\$logs as \$l) {
    echo \$l->agent_name . ' | ' . \$l->status . ' | ' . \$l->error . ' | ' . \$l->duration_ms . 'ms' . PHP_EOL;
}
"

echo ""
echo "=== Message 13 detail ==="
php artisan tinker --execute="
\$m = \App\Models\Message::find(13);
echo 'group_id: ' . \$m->whatsapp_group_id . PHP_EOL;
echo 'sender: ' . \$m->sender_number . PHP_EOL;
echo 'body: ' . \$m->raw_body . PHP_EOL;
echo 'is_processed: ' . \$m->is_processed . PHP_EOL;
echo 'category: ' . \$m->category . PHP_EOL;
"

echo ""
echo "=== Recent errors in log ==="
tail -50 storage/logs/laravel.log | grep -i 'error\|exception\|fail' | tail -10
