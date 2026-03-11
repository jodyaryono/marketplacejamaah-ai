#!/bin/bash
echo "=== Status ==="
curl -s "http://localhost:3001/api/status?token=fc42fe461f106cdee387e807b972b52b"
echo ""

echo "=== Send Test Message ==="
curl -s -X POST http://localhost:3001/api/send \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer fc42fe461f106cdee387e807b972b52b' \
  -d '{"number":"6285719195627","message":"✅ Test dari MarketplaceJamaah AI Bot - connected!"}'
echo ""
