#!/usr/bin/env python3
import subprocess

# Show status distribution
result = subprocess.run(
    ['sudo', '-u', 'postgres', 'psql', '-d', 'marketplacejamaah', '-A', '-F', '|', '-c',
     "SELECT onboarding_status, COUNT(*) as n FROM contacts GROUP BY onboarding_status ORDER BY onboarding_status NULLS FIRST"],
    capture_output=True, text=True
)
print("Status distribution:")
print(result.stdout)

# Check for any group_approval stuck
result2 = subprocess.run(
    ['sudo', '-u', 'postgres', 'psql', '-d', 'marketplacejamaah', '-A', '-F', '|', '-c',
     "SELECT id,phone_number,name,onboarding_status FROM contacts WHERE onboarding_status LIKE 'pending_group_approval%'"],
    capture_output=True, text=True
)
print("group_approval stuck:")
print(result2.stdout if result2.stdout.strip() else "(none)")
if result2.stderr:
    print('ERR:', result2.stderr[:200])
