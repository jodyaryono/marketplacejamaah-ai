#!/bin/bash
cd /var/www/integrasi-wa.jodyaryono.id

echo "=== Stop gateway ==="
supervisorctl stop integrasi-wa
sleep 2

echo "=== Kill orphan Chrome processes ==="
pkill -9 -f chrome 2>/dev/null
sleep 1

echo "=== Clear corrupted Chrome session ==="
rm -rf auth_info/session-6281317647379/
ls auth_info/ 2>/dev/null || echo "auth_info empty"

echo ""
echo "=== Start gateway ==="
supervisorctl start integrasi-wa
sleep 10
tail -10 gateway.log
