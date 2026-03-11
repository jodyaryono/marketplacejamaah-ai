#!/bin/bash
echo "=== Check Laravel webhook log ==="
cd /var/www/marketplacejamaah-ai.jodyaryono.id
tail -20 storage/logs/laravel.log | grep -A5 'Webhook received'

echo ""
echo "=== Check gateway webhook log ==="
grep 'Webhook' /var/www/integrasi-wa.jodyaryono.id/gateway.log | tail -10
