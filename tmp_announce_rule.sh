#!/bin/bash
# Send announcement to Marketplace Jamaah group about status mention rule
curl -s -X POST "http://localhost:3001/api/sendGroup?token=fc42fe461f106cdee387e807b972b52b" \
  -H "Content-Type: application/json" \
  -d '{
    "phone_id": "6281317647379",
    "group": "6285719195627-1540340459@g.us",
    "message": "📢 *INFO PENTING — Marketplace Jamaah*\n\nAssalamualaikum warga Marketplace Jamaah 🙏\n\nMohon diperhatikan ya:\n\n❌ *TIDAK DIPERBOLEHKAN* mempromosikan produk dengan cara *mention grup ini di WhatsApp Status*.\n\n✅ Cara yang benar untuk beriklan:\n1️⃣ Kirim langsung *teks iklan* di grup ini (nama produk, harga, kontak)\n2️⃣ Sertakan *foto/video produk* setelah teks iklan\n3️⃣ Bot akan otomatis memproses dan menayangkan iklan kamu di website marketplace\n\n⚠️ Pesan yang masuk melalui mention Status akan *otomatis dihapus* oleh sistem.\n\nTerima kasih atas kerjasamanya! 🤝\nSemoga jualan kita semua laris manis, barokallah 🤲"
  }'
echo ""
