import subprocess
import json

# Check messages_log for LID 94162896556265 to find associated real phone
sql = "SELECT from_number, to_number, message, media_type, created_at FROM messages_log WHERE from_number = '94162896556265' OR to_number = '94162896556265' ORDER BY created_at DESC LIMIT 10;"
r = subprocess.run(["sudo", "-u", "postgres", "psql", "-d",
                   "integrasi_wa", "-c", sql], capture_output=True, text=True)
print("Messages log (integrasi_wa):", r.stdout or r.stderr)

# Try the marketplacejamaah DB for outgoing bot messages to LID
sql2 = "SELECT id, sender_number, raw_body, created_at FROM messages WHERE sender_number = 'bot' AND created_at > NOW() - INTERVAL '12 hours' ORDER BY id DESC LIMIT 5;"
r2 = subprocess.run(["sudo", "-u", "postgres", "psql", "-d",
                    "marketplacejamaah", "-c", sql2], capture_output=True, text=True)
print("\nBot messages:", r2.stdout or r2.stderr)

# List databases to find correct one
sql3 = "SELECT datname FROM pg_database WHERE datistemplate = false;"
r3 = subprocess.run(["sudo", "-u", "postgres", "psql",
                    "-c", sql3], capture_output=True, text=True)
print("\nDatabases:", r3.stdout or r3.stderr)
