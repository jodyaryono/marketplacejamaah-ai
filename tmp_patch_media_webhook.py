import re

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    code = f.read()

# Patch 1: add media download before contact lookup in forwardToWebhook
old = '        const contact = await msg.getContact();\n        const pushName = contact?.pushname || null;\n        const payload = {'
new = '''        // Download media for image/video/document messages
        let mediaData = null;
        let mediaMimetype = null;
        if (msg.hasMedia) {
            try {
                const m = await msg.downloadMedia();
                if (m) { mediaData = m.data; mediaMimetype = m.mimetype; }
            } catch(e) { console.error('[Webhook] media DL error:', e.message); }
        }
        const contact = await msg.getContact();
        const pushName = contact?.pushname || null;
        const payload = {'''

if old not in code:
    print('ERROR: patch1 target not found')
    print('Looking for similar text...')
    idx = code.find('const contact = await msg.getContact()')
    print(f'Found contact line at char {idx}')
    print(repr(code[max(0, idx-20):idx+80]))
    exit(1)

code = code.replace(old, new, 1)
print('Patch 1 applied: media download added')

# Patch 2: add media_data and media_mimetype to payload
old2 = "            _key: { remoteJid: jid, id: msg.id?._serialized, fromMe: msg.fromMe || false, participant: msg.author || undefined },\n        };"
new2 = """            _key: { remoteJid: jid, id: msg.id?._serialized, fromMe: msg.fromMe || false, participant: msg.author || undefined },
            ...(mediaData ? { media_data: mediaData, media_mimetype: mediaMimetype } : {}),
        };"""

if old2 not in code:
    print('ERROR: patch2 target not found')
    # Try to find _key line
    idx = code.find('_key: { remoteJid: jid')
    print(f'Found _key at char {idx}')
    print(repr(code[max(0, idx-10):idx+120]))
    exit(1)

code = code.replace(old2, new2, 1)
print('Patch 2 applied: media_data added to payload')

with open(path, 'w') as f:
    f.write(code)
print('File written successfully')
