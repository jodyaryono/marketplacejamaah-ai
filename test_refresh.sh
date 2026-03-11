#!/bin/bash
echo "=== Refresh Groups ==="
curl -s -X POST http://localhost:3001/api/refresh-groups \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer fc42fe461f106cdee387e807b972b52b' \
  -d '{}'
echo ""
