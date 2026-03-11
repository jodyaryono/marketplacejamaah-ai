#!/bin/bash
cd /var/www/integrasi-wa.jodyaryono.id
psql -U integrasi_wa -d integrasi_wa -c "SELECT id, session_id, direction, from_number, substring(message,1,60) as msg, media_type, status, created_at FROM messages_log WHERE session_id='6281317647379' AND created_at >= '2026-03-11 15:15:00' ORDER BY id DESC LIMIT 15;"
