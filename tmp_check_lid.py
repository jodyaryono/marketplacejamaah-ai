import subprocess


def run(sql):
    r = subprocess.run(['sudo', '-u', 'postgres', 'psql', '-d',
                       'marketplacejamaah', '-c', sql], capture_output=True, text=True)
    print("SQL:", sql[:80])
    print(r.stdout, r.stderr)
    print()


# Count messages from LID contacts
run("SELECT sender_number, COUNT(*) FROM messages WHERE LENGTH(sender_number) >= 14 GROUP BY sender_number")
# Count agent logs
run("SELECT COUNT(*) FROM agent_logs WHERE input_payload::text LIKE '%72838383972504%' OR input_payload::text LIKE '%91268138926223%'")
# Check listings
run("SELECT COUNT(*) FROM listings WHERE contact_id IN (SELECT id FROM contacts WHERE LENGTH(phone_number) >= 14)")
