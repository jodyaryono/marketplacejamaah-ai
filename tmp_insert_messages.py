#!/usr/bin/env python3
"""Insert group_approval system messages into the DB."""
import subprocess

sql = """
INSERT INTO system_messages (key, "group", label, description, body, placeholders, is_active, sort_order, created_at, updated_at)
VALUES
(
  'group_approval.ask_data',
  'group',
  'Permintaan Join Grup - Minta Data',
  'Dikirim ke calon anggota saat mereka request join grup. Placeholder: {group_name}',
  'Halo! 👋

Ada permintaan bergabung dari kamu ke grup *{group_name}*. 🕌

Sebelum kami setujui, boleh kenalan dulu dong? 😊

Cukup balas dengan perkenalan singkat, misalnya:
_"Jody Aryono, dari Tangerang Selatan, mau jualan dan beli"_

Atau boleh singkat aja:
_"Nama - Kota - Penjual/Pembeli"_

Tidak perlu kaku, yang penting ada nama, domisili, dan kamu mau jual, beli, atau keduanya ya! 🙏',
  '["group_name"]',
  true,
  10,
  NOW(), NOW()
),
(
  'group_approval.retry',
  'group',
  'Permintaan Join Grup - Minta Ulang Data',
  'Dikirim saat data tidak lengkap atau tidak terparsing',
  'Maaf, kami belum bisa menangkap info lengkapnya nih 😅

Yang kami butuhkan cuma:
• *Nama* kamu siapa
• *Domisili* / kota tinggal
• Mau *jual*, *beli*, atau *keduanya*

Contoh: _"Siti, dari Bandung, mau jual kue"_

Bisa dicoba lagi? 🙏',
  '[]',
  true,
  11,
  NOW(), NOW()
),
(
  'group_approval.approved',
  'group',
  'Permintaan Join Grup - Disetujui',
  'Dikirim setelah join request disetujui. Placeholder: {name}, {group_name}, {kota}, {role_label}',
  '✅ *Permintaan Bergabung Disetujui!*

Halo *{name}*! 🎉

Kamu sudah kami setujui bergabung ke grup *{group_name}*.

📝 *Data yang kami catat:*
👤 Nama: {name}
📍 Kota: {kota}
🏷️ Peran: {role_label}

Sebentar lagi kamu akan menerima notifikasi dari WhatsApp. Kami akan menghubungimu kembali untuk melengkapi info. Selamat datang! 🙏',
  '["name","group_name","kota","role_label"]',
  true,
  12,
  NOW(), NOW()
),
(
  'group_approval.processing',
  'group',
  'Permintaan Join Grup - Sedang Diproses',
  'Dikirim saat approve API gagal tapi data sudah valid. Placeholder: {name}, {group_name}, {kota_line}, {role_label}',
  '✅ *Terima kasih, data sudah kami catat!*

Halo *{name}*! 👋

Data kamu sudah kami terima.
📝 Nama: {name}{kota_line}
🏷️ Peran: {role_label}

_Permintaan bergabungmu ke grup *{group_name}* sedang diproses admin. Harap tunggu sebentar ya!_ 🙏',
  '["name","group_name","kota_line","role_label"]',
  true,
  13,
  NOW(), NOW()
)
ON CONFLICT (key) DO UPDATE SET
  body = EXCLUDED.body,
  label = EXCLUDED.label,
  description = EXCLUDED.description,
  placeholders = EXCLUDED.placeholders,
  sort_order = EXCLUDED.sort_order,
  updated_at = NOW();
"""

result = subprocess.run(
    ['sudo', '-u', 'postgres', 'psql', '-d', 'marketplacejamaah'],
    input=sql, capture_output=True, text=True
)
print(result.stdout)
print(result.stderr)
if result.returncode == 0:
    print('DONE')
else:
    import sys
    sys.exit(1)
