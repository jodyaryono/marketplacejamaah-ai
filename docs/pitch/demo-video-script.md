# Demo Video Script — Marketplace Jamaah AI

**Target durasi**: 2.5–3 menit (sweet spot kompetisi: cukup untuk tunjukkan pipeline, tidak terlalu panjang).
**Format**: screen recording + voice-over (OBS / Loom / Zoom rec).
**Resolusi**: 1080p minimum.
**Format file**: MP4 H.264.

---

## ⏱️ Storyboard & Script

### **[0:00–0:15] HOOK — Pesan WAG yang biasa**

**Visual**: Buka WhatsApp Web, tampilkan WAG "Marketplace Jamaah" dengan pesan "Jual Kurma Ajwa Premium 1kg Rp 280.000, Bandung, WA 0858xxx".

**Voice-over (VO)**:
> "Setiap hari di ribuan WAG pengajian, jamaah jualan seperti ini. Tapi pesan kayak gini — biasanya tenggelam dalam ratusan pesan lain dan hilang. Kami mengubah itu."

---

### **[0:15–0:35] PROBLEM → INSIGHT**

**Visual**: 
- Cut ke slide deck slide 2 (Problem) selama 5 detik
- Lalu slide 3 (Insight) selama 5 detik
- Tampilkan logo WhatsApp + arrow ke logo marketplacejamaah-ai

**VO**:
> "Jamaah pengajian gaptek, tidak mau install aplikasi marketplace baru. Tapi mereka semua sudah pakai WhatsApp. Insight kami: jangan paksa mereka pindah platform, bawa platform ke tempat mereka sudah berkumpul. Hasilnya — Marketplace Jamaah AI."

---

### **[0:35–1:15] LIVE DEMO — Pipeline 16 Agents** ⭐ critical

**Visual**: Buka terminal, jalankan command:
```bash
ssh -p 23232 -i ~/.ssh/id_rsa root@103.185.52.146
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan demo:pipeline ad
```

Tunggu output muncul. Highlight bagian:
- Banner "Marketplace Jamaah AI — Multi-Agent Demo Pipeline"
- "Created Message #...: Jual Kurma Ajwa..."
- Tabel AGENT REASONING TRACE — 7 baris, panning pelan
- "Listing Created: id, title, price, contact, location"
- "Total: 19s across 7 agent calls"

**VO** (sync dengan output muncul):
> "Saya jalankan satu command. Pesan WAG masuk, 7 agents berkolaborasi: Onboarding, Parser, Classifier, Moderation, Extractor, Broadcast, AdminReply. Setiap agent — input, output, durasi, reasoning — tercatat. Dalam 19 detik, satu pesan obrolan jadi listing dengan link permanen di marketplace public."

---

### **[1:15–1:40] PUBLIC CATALOG — Eksposur Global**

**Visual**: 
- Buka browser ke `https://marketplacejamaah-ai.jodyaryono.id/p/297`
- Scroll halaman listing — foto, harga, kontak penjual, deskripsi
- Cut ke share button → simulate share ke "Status WA"
- Browser tab jadi peta dunia atau geo highlights (optional)

**VO**:
> "Listing ini punya alamat permanen, bisa di-share di Status WA, WAG lain, sosmed, atau kirim langsung ke keluarga. Pembeli dari Jakarta, Surabaya, bahkan diaspora Muslim di Madinah atau Riyadh, bisa akses produk ini tanpa join WAG. Skill penjual — WhatsApp. Reach — global."

---

### **[1:40–2:10] AI WAG ADMIN — Moderation 24/7**

**Visual**: 
- Tampilkan WAG dengan pesan scam ("Robot trading binary, profit 5juta/hari!")
- Pesan auto-deleted dalam 2 detik
- DM warning notification dari bot ke pengirim
- Cut ke slide 5 (AI WAG Admin) untuk highlight 4 pillar

**VO**:
> "Selain memproses iklan, AI menjadi admin grup 24/7. Scam, judol, riba — auto-detect, auto-warning 3-strike. Pesan non-jualan auto-hapus. Anggota baru di-onboarding via DM. Hadith trading harian dwi-bahasa. Admin manusia bebas tugas, jamaah tetap rasakan grup yang ramah."

---

### **[2:10–2:35] METRICS & ARCHITECTURE**

**Visual**: 
- Slide 8 (Architecture) — pan over 5 layer dengan 16 agent pills
- Cut ke slide 10 (Production metrics) — 3.156 traces, 268 listings, 275 jamaah

**VO**:
> "16 agents tersebar di 5 layer — Listener, Brain, Perception, Action, Output. Single orchestrator, conditional handoff, LLM-agnostic. Production: 3.156 reasoning traces dalam 30 hari, 268 listings auto-created, 275 jamaah ter-onboard. Bukan POC — running real."

---

### **[2:35–3:00] CLOSE — Visi & CTA**

**Visual**:
- Slide 12 (Impact Masyarakat) — Visi ekonomi umat
- Cut ke slide 14 (CTA) — kontak, demo URL, reg code WAGLI4HM
- Logo Marketplace Jamaah AI fade out

**VO**:
> "Kami memimpikan setiap WAG komunitas Muslim di Indonesia jadi micro-marketplace yang aman dan produktif — masjid, RT, halaqah, alumni. Marketplace Jamaah AI: skill jamaah cuma WhatsApp, dampaknya ekonomi umat tanpa kesenjangan. Kompetisi QHomemart 2026, kode pendaftaran WAGLI4HM. Terima kasih."

---

## 🎬 Production Tips

### Recording setup
- **Tool**: OBS Studio (free, local) atau Loom (online, simpler).
- **Mic**: USB mic atau headset — bukan mic laptop bawaan.
- **Volume normalize**: pakai Audacity/Auphonic kalau ada noise.
- **Lighting**: tidak perlu (screen rec).

### Editing
- Cut transitions cepat (max 0.3 detik).
- Tambah background music halus (royalty-free, dari YouTube Audio Library) — lo-fi atau ambient pad. Volume 10-15% dari voice.
- Add captions/subtitle bahasa Indonesia (boost accessibility, beberapa juri mungkin tonton silent).

### Visual cues yang harus ada
- 🏷️ Lower-third overlay untuk slide title saat cut ke deck.
- ⚡ Green highlight box pas tabel agent trace muncul.
- 📍 Map / globe quick frame saat ngomong "Madinah, Riyadh".
- 🔗 URL bar zoom in saat tunjukan permanent link `/p/297`.

### Length check
- 2.5 menit = sweet spot. Kalau under 2 menit terasa rushed, kalau over 3 menit juri bosen.
- Total kalimat di script ini: ~30 kalimat × 5-6 detik per kalimat = ~3 menit. Sesuai target.

### Backup demo (kalau live SSH gagal)
- Pre-record terminal session terpisah, lalu replay di video.
- Atau pakai screenshot static dari hasil sebelumnya (tetap valid asal jujur disebut "hasil dari production sebelumnya").

---

## 📤 Upload & Submission

1. Upload ke YouTube **Unlisted** (link only) — supaya tidak public tapi juri bisa akses.
2. Atau Google Drive shareable link.
3. Embed link di:
   - Submission email ke `event@qhomemart.id`
   - README.md di repo (section "Demo Video")
   - Slide 14 (CTA) — kalau sempat, tambah QR code ke video.

---

## ✅ Self-checklist sebelum upload

- [ ] Audio jelas, tidak ada noise mengganggu
- [ ] Caption/subtitle aktif (auto-gen YouTube atau .srt manual)
- [ ] Durasi 2:30 ± 30 detik
- [ ] Resolusi minimum 1080p
- [ ] Demo command jalan tanpa error (atau pre-recorded backup)
- [ ] URL `marketplacejamaah-ai.jodyaryono.id/p/297` benar live (atau pakai listing aktif lain)
- [ ] Reg code WAGLI4HM disebut minimal sekali
- [ ] Kontak email & repo URL terlihat di frame terakhir
