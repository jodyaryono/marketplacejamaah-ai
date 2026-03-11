import subprocess

# Check constraints on contacts table
sql = "SELECT conname, pg_get_constraintdef(oid) FROM pg_constraint WHERE conrelid = 'contacts'::regclass AND contype = 'c';"
r = subprocess.run(["sudo", "-u", "postgres", "psql", "-d",
                   "marketplacejamaah", "-c", sql], capture_output=True, text=True)
print("CONSTRAINTS:", r.stdout or r.stderr)

# Drop the LID constraint if it exists
sql2 = "ALTER TABLE contacts DROP CONSTRAINT IF EXISTS chk_phone_not_lid;"
r2 = subprocess.run(["sudo", "-u", "postgres", "psql", "-d",
                    "marketplacejamaah", "-c", sql2], capture_output=True, text=True)
print("DROP CONSTRAINT:", r2.stdout or r2.stderr)

# Also check messages table for LID constraints
sql3 = "SELECT conname, pg_get_constraintdef(oid) FROM pg_constraint WHERE conrelid = 'messages'::regclass AND contype = 'c';"
r3 = subprocess.run(["sudo", "-u", "postgres", "psql", "-d",
                    "marketplacejamaah", "-c", sql3], capture_output=True, text=True)
print("MESSAGES CONSTRAINTS:", r3.stdout or r3.stderr)
