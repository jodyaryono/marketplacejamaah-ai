import subprocess
import json

# 1. List groups on WA SERVER AI to check if Marketplace Jamaah is there
r = subprocess.run(["curl", "-s", "http://localhost:3001/api/groups?phone_id=6281317647379", "-H",
                   "Authorization: Bearer fc42fe461f106cdee387e807b972b52b"], capture_output=True, text=True)
try:
    data = json.loads(r.stdout)
    print("=== WA SERVER AI Groups ===")
    for g in data.get("groups", []):
        print(f"  {g.get('subject', '?')} | {g.get('id', '?')}")
except:
    print("Groups response:", r.stdout[:500])

# 2. Search for participants with "Marketplace Jamaah" group to find Regar's number
# Try group-members API for the marketplace group
r2 = subprocess.run(["curl", "-s", "http://localhost:3001/api/group-members", "-X", "POST", "-H", "Authorization: Bearer fc42fe461f106cdee387e807b972b52b",
                    "-H", "Content-Type: application/json", "-d", '{"group_id":"6285719195627-1540340459@g.us","phone_id":"6281317647379"}'], capture_output=True, text=True)
print("\n=== Marketplace Jamaah Members (via WA SERVER AI) ===")
print(r2.stdout[:2000])
