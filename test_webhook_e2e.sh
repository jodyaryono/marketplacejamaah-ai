#!/bin/bash
echo "=== Send message to Test Group AI ==="
curl -s -X POST http://localhost:3001/api/sendGroup \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer fc42fe461f106cdee387e807b972b52b' \
  -d '{"group":"Test Group AI","message":"🧪 Webhook test - pesan ini harus masuk ke Laravel"}'
echo ""

sleep 3

echo "=== Gateway webhook log ==="
grep 'Webhook' /var/www/integrasi-wa.jodyaryono.id/gateway.log | tail -5

echo ""
echo "=== Laravel webhook log ==="
cd /var/www/marketplacejamaah-ai.jodyaryono.id
grep 'Webhook received' storage/logs/laravel.log | tail -3
