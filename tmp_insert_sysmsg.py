import subprocess

sql = (
    'INSERT INTO system_messages (key, "group", label, body, created_at, updated_at) '
    "VALUES ('bot.unregistered_dm', 'bot', 'Pesan DM - Belum Terdaftar', "
    "'Halo! \U0001f44b\\n\\nKamu belum terdaftar di *Marketplace Jamaah*."
    "\\n\\nUntuk menggunakan fitur bot, silakan bergabung ke WhatsApp Group kami terlebih dahulu:"
    "\\n{wag_link}"
    "\\n\\n_Setelah bergabung dan mendaftar di grup, semua fitur bot bisa kamu gunakan._ \U0001f64f', "
    "NOW(), NOW()) ON CONFLICT (key) DO NOTHING;"
)

result = subprocess.run(
    ['sudo', '-u', 'postgres', 'psql', '-d', 'marketplacejamaah', '-c', sql],
    capture_output=True, text=True
)
print("STDOUT:", result.stdout)
print("STDERR:", result.stderr)
print("RC:", result.returncode)
