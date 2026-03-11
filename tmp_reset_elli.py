import subprocess

# Reset Elli to null/pending so next message triggers fresh onboarding
# (her LID JID will be cached on next incoming message, then send works)
q_reset = "UPDATE contacts SET onboarding_status=NULL, is_registered=FALSE WHERE phone_number='187758941335796';"
r = subprocess.run(['sudo', '-u', 'postgres', 'psql', '-d', 'marketplacejamaah', '-c', q_reset], capture_output=True, text=True)
print('Reset:', r.stdout.strip(), r.stderr.strip())

# Also check other @lid contacts stuck at pending_seller/buyer/both_products
q_check = "SELECT id, phone_number, name, onboarding_status FROM contacts WHERE onboarding_status IN ('pending_seller_products','pending_buyer_products','pending_both_products');"
r2 = subprocess.run(['sudo', '-u', 'postgres', 'psql', '-d', 'marketplacejamaah', '-A', '-c', q_check], capture_output=True, text=True)
print('Stuck at product step:')
print(r2.stdout)
