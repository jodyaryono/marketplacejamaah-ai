#!/bin/bash
PGPASSWORD=integrasi2026 psql -U integrasi_wa -d integrasi_wa -c "\dt"
echo "---"
PGPASSWORD=integrasi2026 psql -U integrasi_wa -d integrasi_wa -c "SELECT phone_id, label, status FROM wa_sessions;" 2>/dev/null
echo "---"
PGPASSWORD=integrasi2026 psql -U integrasi_wa -d integrasi_wa -c "SELECT phone_id, label, status FROM phones;" 2>/dev/null
