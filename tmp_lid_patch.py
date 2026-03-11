import re

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    src = f.read()

OLD = "        const contact = await msg.getContact();\n        const pushName = contact?.pushname || null;"
NEW = """        const contact = await msg.getContact();
        const pushName = contact?.pushname || null;
        // Resolve WhatsApp LID to actual phone number (LID is an internal WA ID, not a real phone)
        const _senderJid = isGroup ? (msg.author || '') : jid;
        if (_senderJid.endsWith('@lid')) {
            const _realPhone = (contact && contact.number && /^\d{7,15}$/.test(contact.number) ? contact.number : null)
                || (contact && contact.id && contact.id._serialized && !contact.id._serialized.endsWith('@lid') ? contact.id.user : null);
            if (_realPhone) fromNum = _realPhone;
        }"""

if OLD not in src:
    print('PATTERN NOT FOUND')
    exit(1)
if src.count(OLD) > 1:
    print('AMBIGUOUS MATCH')
    exit(1)
patched = src.replace(OLD, NEW, 1)
with open(path, 'w') as f:
    f.write(patched)
print('OK')
