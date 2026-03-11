#!/bin/bash
echo "=== Send to Devtest Group ==="
RESULT=$(curl -s -X POST http://localhost:3001/api/sendGroup \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer fc42fe461f106cdee387e807b972b52b' \
  -d '{"group":"Devtest","message":"🧪 Test pesan - akan dihapus dalam 5 detik..."}')
echo "$RESULT"

# Extract message id
MSG_ID=$(echo "$RESULT" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['id'])")
GROUP_JID=$(echo "$RESULT" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['group_jid'])")
echo ""
echo "Message ID: $MSG_ID"
echo "Group JID: $GROUP_JID"

sleep 5

echo ""
echo "=== Delete Message ==="
curl -s -X POST http://localhost:3001/api/delete \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer fc42fe461f106cdee387e807b972b52b' \
  -d "{\"group_id\":\"$GROUP_JID\",\"message_id\":\"$MSG_ID\"}"
echo ""
