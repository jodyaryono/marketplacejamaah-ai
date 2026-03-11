#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
DB_USER=$(grep '^DB_USERNAME' .env | cut -d'=' -f2-)
DB_PASS=$(grep '^DB_PASSWORD' .env | cut -d'=' -f2-)
DB_NAME=$(grep '^DB_DATABASE' .env | cut -d'=' -f2-)
DB_HOST=$(grep '^DB_HOST' .env | cut -d'=' -f2-)
export PGPASSWORD="$DB_PASS"
echo "=== Last 5 BotQueryAgent logs ==="
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT id, status, input_payload, output_payload, error, created_at FROM agent_logs WHERE agent_name='BotQueryAgent' ORDER BY id DESC LIMIT 5;"
