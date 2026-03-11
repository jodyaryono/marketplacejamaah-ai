#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
DB_USER=$(grep '^DB_USERNAME' .env | cut -d'=' -f2-)
DB_PASS=$(grep '^DB_PASSWORD' .env | cut -d'=' -f2-)
DB_NAME=$(grep '^DB_DATABASE' .env | cut -d'=' -f2-)
DB_HOST=$(grep '^DB_HOST' .env | cut -d'=' -f2-)
export PGPASSWORD="$DB_PASS"
echo "=== Test ilike query ==="
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT l.id, l.title FROM listings l JOIN categories c ON l.category_id=c.id WHERE l.status='active' AND c.name ILIKE '%Makanan%';"
echo "=== Test with full name ==="
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT l.id, l.title FROM listings l JOIN categories c ON l.category_id=c.id WHERE l.status='active' AND c.name ILIKE '%Makanan & Minuman%';"
echo "=== Wherehas equivalent ==="
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT id, title FROM listings WHERE status='active' AND EXISTS (SELECT 1 FROM categories WHERE categories.id=listings.category_id AND categories.name ILIKE '%Makanan%');"
