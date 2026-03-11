import subprocess


def run(sql, label=''):
    r = subprocess.run(['sudo', '-u', 'postgres', 'psql', '-d',
                       'marketplacejamaah', '-c', sql], capture_output=True, text=True)
    if label:
        print(f"=== {label} ===")
    print(r.stdout.strip(), r.stderr.strip() if r.stderr.strip() else '')
    print()


# Show what we're about to delete
run("SELECT id, phone_number, name, message_count FROM contacts WHERE LENGTH(phone_number) >= 14",
    "LID contacts to delete")
run("SELECT sender_number, COUNT(*) as cnt FROM messages WHERE LENGTH(sender_number) >= 14 GROUP BY sender_number", "LID messages to delete")

# Delete messages from LID numbers (14+ digit phone numbers are always WA LIDs, never real E.164)
run("DELETE FROM messages WHERE LENGTH(sender_number) >= 14 RETURNING id, sender_number",
    "Deleted LID messages")

# Delete contacts with LID numbers
run("DELETE FROM contacts WHERE LENGTH(phone_number) >= 14 RETURNING id, phone_number, name",
    "Deleted LID contacts")

print("=== CLEANUP DONE ===")
