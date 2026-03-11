path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Find the two target lines
idx_fromNum  = next((i for i,l in enumerate(lines) if 'const fromNum = isGroup' in l), None)
idx_contact  = next((i for i,l in enumerate(lines) if 'const contact = await msg.getContact()' in l), None)
idx_pushName = next((i for i,l in enumerate(lines) if 'const pushName = contact?.pushname' in l), None)

print(f'idx_fromNum={idx_fromNum}  idx_contact={idx_contact}  idx_pushName={idx_pushName}')

if idx_fromNum is None or idx_contact is None or idx_pushName is None:
    print('TARGET LINES NOT FOUND'); exit(1)

# Change 1: const fromNum -> let fromNum
lines[idx_fromNum] = lines[idx_fromNum].replace('const fromNum', 'let fromNum', 1)

# Change 2: insert LID resolution right after the pushName line
lid_block = [
    "        // Resolve WhatsApp LID to actual phone number (LID != real phone)\n",
    "        const _senderJid = isGroup ? (msg.author || '') : jid;\n",
    "        if (_senderJid.endsWith('@lid')) {\n",
    "            const _realPhone = (contact && contact.number && /^\\d{7,15}$/.test(contact.number)\n",
    "                ? contact.number : null)\n",
    "                || (!contact?.id?._serialized?.endsWith('@lid') && contact?.id?.user\n",
    "                ? contact.id.user : null);\n",
    "            if (_realPhone) fromNum = _realPhone;\n",
    "        }\n",
]
lines = lines[:idx_pushName+1] + lid_block + lines[idx_pushName+1:]

with open(path, 'w', encoding='utf-8') as f:
    f.writelines(lines)
print('OK')
