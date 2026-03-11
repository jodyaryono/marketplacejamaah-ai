import subprocess

# Find Regar messages
sql = "SELECT id, sender_number, sender_name, message_type, raw_body, is_processed, created_at FROM messages WHERE sender_name ILIKE '%regar%' OR sender_name ILIKE '%Regar%' ORDER BY id DESC LIMIT 10;"
r = subprocess.run(["sudo", "-u", "postgres", "psql", "-d",
                   "marketplacejamaah", "-c", sql], capture_output=True, text=True)
print("REGAR MESSAGES:", r.stdout or r.stderr)

# Recent agent logs for one_liner or deleted
sql2 = "SELECT id, agent_name, status, input_payload, output_payload, created_at FROM agent_logs WHERE created_at > NOW() - INTERVAL '6 hours' AND output_payload::text ILIKE '%one_liner%' ORDER BY id DESC LIMIT 10;"
r2 = subprocess.run(["sudo", "-u", "postgres", "psql", "-d",
                    "marketplacejamaah", "-c", sql2], capture_output=True, text=True)
print("ONE LINER LOGS:", r2.stdout or r2.stderr)

# All recent messages last 2 hours
sql3 = "SELECT id, sender_number, sender_name, message_type, left(raw_body,80), is_processed, created_at FROM messages WHERE created_at > NOW() - INTERVAL '2 hours' ORDER BY id DESC LIMIT 15;"
r3 = subprocess.run(["sudo", "-u", "postgres", "psql", "-d",
                    "marketplacejamaah", "-c", sql3], capture_output=True, text=True)
print("RECENT MESSAGES:", r3.stdout or r3.stderr)
