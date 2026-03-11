#!/bin/bash
curl -s -X POST https://integrasi-wa.jodyaryono.id/api/send \
  -H 'Content-Type: application/json' \
  -d '{
  "phone_id": "6281317647379",
  "token": "fc42fe461f106cdee387e807b972b52b",
  "number": "6285719195627-1540340459@g.us",
  "message": "Assalamu'\''alaikum teman-teman Marketplace Jamaah! \ud83d\ude4f\n\n\ud83d\udce2 *Info Penting soal Iklan*\n\nSaat ini ada *2 jenis iklan* yang bisa dipasang:\n\n\ud83d\uddbc\ufe0f *Etalase* \u2014 Iklan yang disertai gambar/video.\nKalau posting iklan di grup *pakai foto atau video*, otomatis akan tampil di *Etalase*.\n\n\ud83d\udcdd *Iklan Baris* \u2014 Iklan tanpa media.\nKalau posting iklan *hanya teks* tanpa gambar/video, akan masuk ke *Iklan Baris*.\n\nJadi pastikan sertakan foto/video produk kalau mau tampil di Etalase ya! \ud83d\ude0a\n\nKalau ada pertanyaan, langsung *chat/wapri bot ini* aja ya. Insya Allah dibantu. \ud83d\udcac\n\nJazakallahu khairan \ud83e\udd32"
}'
