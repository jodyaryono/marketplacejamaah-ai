#!/bin/bash
echo "=== Simulate incoming group message to Laravel webhook ==="
curl -s -X POST https://marketplacejamaah-ai.jodyaryono.id/api/webhook/whacenter \
  -H 'Content-Type: application/json' \
  -d '{
    "phone_id":"6281317647379",
    "message_id":"webhook_e2e_test_001",
    "message":"test webhook dari group",
    "type":"text",
    "timestamp":1741411200,
    "sender":"6285719195627",
    "sender_name":"Jody",
    "from":"120363424556083297",
    "pushname":"Jody",
    "group_id":"120363424556083297@g.us",
    "from_group":"120363424556083297@g.us",
    "group_name":"Test Group AI",
    "_key":{"remoteJid":"120363424556083297@g.us","id":"webhook_e2e_test_001","fromMe":false,"participant":"6285719195627@c.us"}
  }'
echo ""

sleep 2

echo "=== Latest messages in DB ==="
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute="dump(\App\Models\Message::latest()->take(3)->get(['id','sender_number','sender_name','raw_body','whatsapp_group_id','sent_at'])->toArray());"

echo ""
echo "=== Queue jobs ==="
php artisan tinker --execute="dump(\App\Models\AgentLog::latest()->take(3)->get(['id','agent_name','status','message_id'])->toArray());"
