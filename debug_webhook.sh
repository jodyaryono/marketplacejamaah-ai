#!/bin/bash
echo "=== Gateway webhook log ==="
grep 'Webhook' /var/www/integrasi-wa.jodyaryono.id/gateway.log | tail -10

echo ""
echo "=== Gateway status ==="
curl -s "http://localhost:3001/api/status?token=fc42fe461f106cdee387e807b972b52b"

echo ""
echo "=== Laravel latest messages ==="
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute="dump(\App\Models\Message::latest()->take(5)->get(['id','sender_number','raw_body','whatsapp_group_id','created_at'])->toArray());"

echo ""
echo "=== Laravel latest logs ==="
tail -30 storage/logs/laravel.log | grep -E 'Webhook|error|Error|queue|dispatch'

echo ""
echo "=== Queue worker status ==="
supervisorctl status | grep marketplace

echo ""
echo "=== Failed jobs ==="
php artisan tinker --execute="dump(\DB::table('failed_jobs')->latest()->take(3)->get(['id','payload','failed_at'])->toArray());"
