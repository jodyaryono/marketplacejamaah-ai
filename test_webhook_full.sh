#!/bin/bash
echo "=== Send to Test Group AI ==="
curl -s -X POST http://localhost:3001/api/sendGroup \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer fc42fe461f106cdee387e807b972b52b' \
  -d '{"group":"Test Group AI","message":"🧪 Webhook test - cek apakah masuk Laravel"}'
echo ""

sleep 5

echo "=== Gateway webhook log ==="
grep 'Webhook' /var/www/integrasi-wa.jodyaryono.id/gateway.log | tail -5

echo ""
echo "=== Laravel log ==="
cd /var/www/marketplacejamaah-ai.jodyaryono.id
grep 'Webhook received' storage/logs/laravel.log | tail -3

echo ""
echo "=== Messages in DB ==="
php artisan tinker --execute="dump(\App\Models\Message::latest()->take(3)->get(['id','sender_number','raw_body','sent_at'])->toArray());"
