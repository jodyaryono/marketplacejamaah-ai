#!/bin/bash
psql -U postgres -d marketplacejamaah -c "SELECT id, sender_number, raw_body, is_processed, created_at FROM messages WHERE raw_body ILIKE '%bantuan%' ORDER BY id DESC LIMIT 5;"
