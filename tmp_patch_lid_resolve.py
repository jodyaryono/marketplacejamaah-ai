import re

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    code = f.read()

# ── PATCH: Resolve LID → real phone number in forwardToWebhook ──
# Replace the fromNum + log block with LID resolution logic

old = """    const cleanJid = (j) => j ? j.replace(/@(c\\.us|s\\.whatsapp\\.net|lid|newsletter|broadcast|g\\.us)$/i, '') : '';
    const fromNum = isGroup ? cleanJid(msg.author || '') : cleanJid(jid);"""

new = """    const cleanJid = (j) => j ? j.replace(/@(c\\.us|s\\.whatsapp\\.net|lid|newsletter|broadcast|g\\.us)$/i, '') : '';
    let fromNum = isGroup ? cleanJid(msg.author || '') : cleanJid(jid);
    let senderLid = null;
    // Resolve LID → real phone number (LID = 14+ digits, not a real phone)
    const authorJid = isGroup ? (msg.author || '') : (jid || '');
    if (authorJid.endsWith('@lid') || (fromNum.length >= 14 && /^\\d+$/.test(fromNum))) {
        senderLid = fromNum;
        try {
            const sess = sessions.get(phoneId);
            if (sess?.client) {
                const senderContact = await sess.client.getContactById(authorJid.includes('@') ? authorJid : fromNum + '@lid');
                if (senderContact?.number) {
                    let realNum = String(senderContact.number).replace(/\\D/g, '');
                    if (realNum.startsWith('0')) realNum = '62' + realNum.slice(1);
                    fromNum = realNum;
                    console.log('[LID→Phone] Resolved', senderLid, '→', fromNum);
                } else {
                    console.warn('[LID→Phone] Contact has no number for', senderLid, '- keeping LID as sender');
                }
            }
        } catch (e) { console.warn('[LID→Phone] Failed to resolve', senderLid, ':', e.message); }
    }"""

if old not in code:
    print('ERROR: patch target not found')
    # Debug: find the cleanJid line
    idx = code.find('const cleanJid')
    print(f'cleanJid at char {idx}')
    print(repr(code[max(0, idx-10):idx+200]))
    exit(1)

code = code.replace(old, new, 1)
print('Patch 1 applied: LID resolution in forwardToWebhook')

# ── PATCH 2: Add sender_lid to webhook payload ──
old2 = '            _key: { remoteJid: jid, id: msg.id?._serialized, fromMe: msg.fromMe || false, participant: msg.author || undefined },'
new2 = '            _key: { remoteJid: jid, id: msg.id?._serialized, fromMe: msg.fromMe || false, participant: msg.author || undefined },\n            ...(senderLid ? { sender_lid: senderLid } : {}),'

if old2 not in code:
    print('ERROR: patch2 _key target not found')
    idx = code.find('_key: { remoteJid')
    print(f'_key at char {idx}')
    print(repr(code[max(0, idx-10):idx+200]))
    exit(1)

code = code.replace(old2, new2, 1)
print('Patch 2 applied: sender_lid in payload')

with open(path, 'w') as f:
    f.write(code)
print('File written successfully')
