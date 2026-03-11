import sys

filepath = '/var/www/integrasi-wa.jodyaryono.id/index.js'

with open(filepath, 'r') as f:
    content = f.read()

old = """        const contact = await msg.getContact();
        const pushName = contact?.pushname || null;
        const payload = {
            phone_id: phoneId, message_id: msg.id?._serialized, message: textContent,
            type: contentType?.replace('Message', '') || 'text',
            timestamp: msg.timestamp || Math.floor(Date.now() / 1000),
            sender: fromNum, sender_name: pushName, from: jid.replace('@c.us', '').replace('@g.us', ''),
            pushname: pushName,
            ...(isGroup ? { group_id: jid, from_group: jid, group_name: groupCache.get(jid)?.subject || jid } : {}),
            _key: { remoteJid: jid, id: msg.id?._serialized, fromMe: msg.fromMe || false, participant: msg.author || undefined },
        };"""

new = """        const contact = await msg.getContact();
        const pushName = contact?.pushname || null;
        // Download and save media if present
        let savedMediaUrl = null;
        if (msg.hasMedia) {
            try {
                const media = await msg.downloadMedia();
                if (media?.data) {
                    const rawExt = (media.mimetype || '').split('/')[1]?.split(';')[0] || 'bin';
                    const ext = rawExt === 'jpeg' ? 'jpg' : rawExt;
                    const fname = 'wa_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8) + '.' + ext;
                    const uploadDir = '/var/www/integrasi-wa.jodyaryono.id/uploads';
                    if (!fs.existsSync(uploadDir)) fs.mkdirSync(uploadDir, { recursive: true });
                    fs.writeFileSync(path.join(uploadDir, fname), Buffer.from(media.data, 'base64'));
                    savedMediaUrl = 'https://integrasi-wa.jodyaryono.id/uploads/' + fname;
                    console.log('[Media] saved ' + fname + ' (' + media.mimetype + ')');
                }
            } catch (me) { console.error('[Media] download failed', me.message); }
        }
        const payload = {
            phone_id: phoneId, message_id: msg.id?._serialized, message: textContent,
            type: contentType?.replace('Message', '') || 'text',
            timestamp: msg.timestamp || Math.floor(Date.now() / 1000),
            sender: fromNum, sender_name: pushName, from: jid.replace('@c.us', '').replace('@g.us', ''),
            pushname: pushName,
            media_url: savedMediaUrl,
            ...(isGroup ? { group_id: jid, from_group: jid, group_name: groupCache.get(jid)?.subject || jid } : {}),
            _key: { remoteJid: jid, id: msg.id?._serialized, fromMe: msg.fromMe || false, participant: msg.author || undefined },
        };"""

if old in content:
    content = content.replace(old, new)
    with open(filepath, 'w') as f:
        f.write(content)
    print("PATCHED OK")
else:
    print("ERROR: pattern not found")
    # Print surrounding context for debugging
    idx = content.find("const pushName = contact?.pushname")
    print("Context around pushName:", repr(content[idx-50:idx+200]))
    sys.exit(1)
