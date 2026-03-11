import json
import urllib.request

msg = """Assalamu'alaikum teman-teman Marketplace Jamaah! 🙏

📢 *Info Penting soal Iklan*

Saat ini ada *2 jenis iklan* yang bisa dipasang:

🖼️ *Etalase* — Iklan yang disertai gambar/video.
Kalau posting iklan di grup *pakai foto atau video*, otomatis akan tampil di *Etalase*.

📝 *Iklan Baris* — Iklan tanpa media.
Kalau posting iklan *hanya teks* tanpa gambar/video, akan masuk ke *Iklan Baris*.

Jadi pastikan sertakan foto/video produk kalau mau tampil di Etalase ya! 😊

Kalau ada pertanyaan, langsung *chat/wapri bot ini* aja ya. Insya Allah dibantu. 💬

Jazakallahu khairan 🤲"""

data = json.dumps({
    "phone_id": "6281317647379",
    "token": "fc42fe461f106cdee387e807b972b52b",
    "group": "6285719195627-1540340459@g.us",
    "message": msg
}).encode()

req = urllib.request.Request(
    "https://integrasi-wa.jodyaryono.id/api/sendGroup",
    data,
    {"Content-Type": "application/json"}
)
try:
    resp = urllib.request.urlopen(req)
    print(resp.read().decode())
except urllib.error.HTTPError as e:
    print(f"HTTP {e.code}: {e.read().decode()}")
