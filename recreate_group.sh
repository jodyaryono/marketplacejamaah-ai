#!/bin/bash
echo "=== Recreate Test Group AI + add 085719195627 ==="
curl -s -X POST http://localhost:3001/api/create-group \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer fc42fe461f106cdee387e807b972b52b' \
  -d '{"name":"Test Group AI","members":["6285719195627"]}'
echo ""
