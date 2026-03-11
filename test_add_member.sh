#!/bin/bash
echo "=== Add 085719195627 to Devtest ==="
curl -s -X POST http://localhost:3001/api/add-member \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer fc42fe461f106cdee387e807b972b52b' \
  -d '{"group_id":"120363418819831746@g.us","members":["6285719195627"]}'
echo ""
