#!/bin/bash
echo "=== Test webhook endpoint directly ==="
curl -s -X POST https://marketplacejamaah-ai.jodyaryono.id/api/webhook/whacenter \
  -H 'Content-Type: application/json' \
  -d '{
    "phone_id":"6281317647379",
    "message_id":"test_webhook_123",
    "message":"Halo ini test webhook",
    "type":"text",
    "timestamp":1741430400,
    "sender":"6285719195627",
    "sender_name":"Jody",
    "from":"6285719195627",
    "pushname":"Jody"
  }'
echo ""

echo "=== Check Laravel log ==="
cd /var/www/marketplacejamaah-ai.jodyaryono.id
tail -5 storage/logs/laravel.log | grep -c 'Webhook received'
echo "webhook entries found"
