#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
DB_USER=$(grep '^DB_USERNAME' .env | cut -d'=' -f2-)
DB_PASS=$(grep '^DB_PASSWORD' .env | cut -d'=' -f2-)
DB_NAME=$(grep '^DB_DATABASE' .env | cut -d'=' -f2-)
DB_HOST=$(grep '^DB_HOST' .env | cut -d'=' -f2-)
export PGPASSWORD="$DB_PASS"
echo "=== Pending jobs ==="
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT queue, COUNT(*) FROM jobs GROUP BY queue;"
echo "=== Failed jobs ==="
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT id, queue, LEFT(payload::text,120), failed_at FROM failed_jobs ORDER BY failed_at DESC LIMIT 5;"
