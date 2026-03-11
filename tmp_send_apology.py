import json
import urllib.request

data = {
    "phone_id": "6281317647379",
    "number": "6281413033339",
    "message": (
        "Kak Babeh, maaf banget ya tadi balesannya bikin bingung! 😅\n\n"
        "Aku admin Marketplace Jamaah, masih belajar nih jadi kadang jawabannya agak kaku hehe.\n\n"
        "Aku paham kok kak, kakak emang udah di grup Marketplace Jamaah dari awal. "
        "Cuma di sistem kami kakak belum terdaftar resmi aja.\n\n"
        "Biar aku daftarin, boleh kasih tau:\n"
        "- Nama lengkap kakak\n"
        "- Tinggal di kota mana\n"
        "- Mau jualan, belanja, atau dua-duanya?\n\n"
        "Bales aja bebas ya kak, gak perlu format khusus 😊"
    )
}

payload = json.dumps(data).encode("utf-8")
req = urllib.request.Request(
    "http://localhost:3001/api/send",
    data=payload,
    headers={
        "Content-Type": "application/json",
        "Authorization": "Bearer fc42fe461f106cdee387e807b972b52b"
    },
    method="POST"
)

try:
    with urllib.request.urlopen(req, timeout=30) as resp:
        print(f"Status: {resp.status}")
        print(resp.read().decode())
except Exception as e:
    print(f"Error: {e}")
    if hasattr(e, 'read'):
        print(e.read().decode())
