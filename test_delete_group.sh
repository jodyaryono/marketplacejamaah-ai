#!/bin/bash
echo "=== Delete Group: Test Group AI ==="
curl -s -X POST http://localhost:3001/api/delete-group \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer fc42fe461f106cdee387e807b972b52b' \
  -d '{"group_id":"120363422383417015@g.us"}'
echo ""

echo "=== Groups After Delete ==="
curl -s "http://localhost:3001/api/groups?token=fc42fe461f106cdee387e807b972b52b"
echo ""
