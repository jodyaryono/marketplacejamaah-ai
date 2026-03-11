import subprocess

sql = "SELECT id, message_type, sender_number, created_at FROM messages WHERE sender_number LIKE '6285711746691%' ORDER BY id DESC LIMIT 10;"
r = subprocess.run(["sudo", "-u", "postgres", "psql", "-d",
                   "marketplacejamaah", "-c", sql], capture_output=True, text=True)
print("STDOUT:", r.stdout)
print("STDERR:", r.stderr)

# Also check listings
sql2 = "SELECT id, title, created_at FROM listings WHERE created_at > NOW() - INTERVAL '24 hours' ORDER BY id DESC LIMIT 10;"
r2 = subprocess.run(["sudo", "-u", "postgres", "psql", "-d",
                    "marketplacejamaah", "-c", sql2], capture_output=True, text=True)
print("RECENT LISTINGS:", r2.stdout)

# Check agent logs for image processing
sql3 = "SELECT id, agent_name, status, created_at, left(input_summary,100) as inp FROM agent_logs WHERE created_at > NOW() - INTERVAL '12 hours' ORDER BY id DESC LIMIT 20;"
r3 = subprocess.run(["sudo", "-u", "postgres", "psql", "-d",
                    "marketplacejamaah", "-c", sql3], capture_output=True, text=True)
print("RECENT AGENT LOGS:", r3.stdout)
