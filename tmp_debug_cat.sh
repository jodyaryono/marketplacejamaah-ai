#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
DB_USER=$(grep '^DB_USERNAME' .env | cut -d'=' -f2-)
DB_PASS=$(grep '^DB_PASSWORD' .env | cut -d'=' -f2-)
DB_NAME=$(grep '^DB_DATABASE' .env | cut -d'=' -f2-)
DB_HOST=$(grep '^DB_HOST' .env | cut -d'=' -f2-)
PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" << 'SQL'
SELECT l.id, l.title, l.status, c.name as cat FROM listings l LEFT JOIN categories c ON l.category_id=c.id LIMIT 10;
SELECT DISTINCT name FROM categories WHERE is_active=true;
SQL
