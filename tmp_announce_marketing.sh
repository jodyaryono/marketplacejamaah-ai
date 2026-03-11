#!/bin/bash
# Send shareable marketing message to Marketplace Jamaah group
curl -s -X POST "http://localhost:3001/api/sendGroup?token=fc42fe461f106cdee387e807b972b52b" \
  -H "Content-Type: application/json" \
  -d '{
    "phone_id": "6281317647379",
    "group": "6285719195627-1540340459@g.us",
    "message": "🛒✨ *MARKETPLACE JAMAAH — Gratis & Mudah!* ✨🛒\n\nMau jualan online tapi ribet bikin toko?\nDi sini cukup *3 langkah* aja:\n\n1️⃣ *Join* grup WhatsApp ini\n2️⃣ *Kirim iklan* langsung di grup (tulis nama barang + harga + foto)\n3️⃣ *Selesai!* Iklan kamu langsung tayang di website marketplace 🚀\n\n💡 *Keunggulan:*\n✅ 100% GRATIS — tanpa biaya apapun\n✅ Cukup dari WhatsApp — tidak perlu download aplikasi lain\n✅ Iklan otomatis tayang di website dengan foto & video\n✅ Bisa *edit iklan* langsung dari WhatsApp (DM bot)\n✅ Calon pembeli bisa langsung hubungi kamu via WA\n✅ Dikelola oleh AI — iklan rapi & profesional otomatis\n\n🌐 Lihat marketplace: https://marketplacejamaah-ai.jodyaryono.id\n\n📲 *Link Join Grup:*\nhttps://chat.whatsapp.com/F2SA2usTXXSFXYgJyvcX3k\n\n_Silakan share/forward pesan ini ke teman, keluarga, atau grup lain ya! Semakin ramai semakin berkah_ 🤲\n\n#MarketplaceJamaah #JualanGratis #MarketplaceWhatsApp"
  }'
echo ""
