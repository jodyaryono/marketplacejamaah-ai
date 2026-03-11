#!/bin/bash
export PGPASSWORD=423525
echo "=== Contact ibu Ana ==="
psql -U postgres -h 127.0.0.1 -d marketplacejamaah -c "SELECT id, phone_number, name, is_registered, warning_count, is_blocked FROM contacts WHERE name ILIKE '%ana%' OR name ILIKE '%ibu%' ORDER BY id;"
echo ""
echo "=== Recent messages last 2 hours ==="
psql -U postgres -h 127.0.0.1 -d marketplacejamaah -c "SELECT m.id, m.sender_number, m.sender_name, LEFT(m.raw_body,60) as body, m.message_type, m.created_at FROM messages m WHERE m.created_at > NOW() - INTERVAL '2 hours' ORDER BY m.id DESC LIMIT 15;"
echo ""
echo "=== Agent logs last 2 hours ==="
psql -U postgres -h 127.0.0.1 -d marketplacejamaah -c "SELECT id, agent_name, message_id, status, LEFT(error,80) as error, created_at FROM agent_logs WHERE created_at > NOW() - INTERVAL '2 hours' ORDER BY id DESC LIMIT 20;"
