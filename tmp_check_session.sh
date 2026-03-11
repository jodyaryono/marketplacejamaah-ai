#!/bin/bash
PGPASSWORD=integrasi2026 psql -U integrasi_wa -d integrasi_wa -c "SELECT phone_id, label, status FROM sessions;"
