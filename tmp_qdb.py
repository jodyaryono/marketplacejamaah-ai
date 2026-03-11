import subprocess, json
q = "SELECT id, phone_number, name, onboarding_status, is_registered FROM contacts WHERE phone_number IN ('187758941335796', '6285742423505') ORDER BY id;"
r = subprocess.run(['sudo', '-u', 'postgres', 'psql', '-d', 'marketplacejamaah', '-A', '-c', q], capture_output=True, text=True)
print(r.stdout)
print(r.stderr)
