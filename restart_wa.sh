#!/bin/bash
# Login to dashboard and restart WA session
COOKIE_JAR=/tmp/wa_cookies.txt

# Login
curl -s -c $COOKIE_JAR -X POST http://localhost:3001/login \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'username=admin&password=admin123'

echo "=== Login done ==="

# Restart session
curl -s -b $COOKIE_JAR -X POST http://localhost:3001/web/session/6281317647379/restart \
  -H 'Content-Type: application/json'

echo ""
echo "=== Restart done ==="

# Wait for initialization
sleep 15

# Check status
curl -s "http://localhost:3001/api/status?token=fc42fe461f106cdee387e807b972b52b"
echo ""

echo "=== Log ==="
tail -10 /var/www/integrasi-wa.jodyaryono.id/gateway.log
