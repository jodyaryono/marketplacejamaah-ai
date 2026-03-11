import json
import urllib.request

data = json.dumps({
    "phone_id": "6281317647379",
    "token": "fc42fe461f106cdee387e807b972b52b",
    "number": "6285719195627-1540340459@g.us",
    "message": "Test kirim dari bot"
}).encode()

req = urllib.request.Request(
    "https://integrasi-wa.jodyaryono.id/api/send",
    data,
    {"Content-Type": "application/json"}
)
try:
    resp = urllib.request.urlopen(req)
    print(resp.read().decode())
except urllib.error.HTTPError as e:
    print(f"HTTP {e.code}: {e.read().decode()}")
