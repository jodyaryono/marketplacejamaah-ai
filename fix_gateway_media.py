import re

filepath = "/var/www/integrasi-wa.jodyaryono.id/index.js"

with open(filepath, "r") as f:
    content = f.read()

# Block 1: Add media download code after pushName
old_block = """        const contact = await msg.getContact();
        const pushName = contact?.pushname || null;
        const payload = {
            phone_id: phoneId, message_id: msg.id?._serialized, message: textContent,"""

new_block = """        const contact = await msg.getContact();
        const pushName = contact?.pushname || null;
        // Download media if present
        let mediaData = null;
        let mediaMimetype = null;
        let mediaFilename = null;
        if (msg.hasMedia) {
            try {
                const media = await msg.downloadMedia();
                if (media) {
                    mediaData = media.data;       // base64 string
                    mediaMimetype = media.mimetype;
                    mediaFilename = media.filename || null;
                }
            } catch (mErr) { console.error('[Webhook][' + phoneId + '] media download failed:', mErr.message); }
        }
        const payload = {
            phone_id: phoneId, message_id: msg.id?._serialized, message: textContent,"""

if old_block in content:
    content = content.replace(old_block, new_block, 1)
    print("Block 1 replaced OK")
else:
    print("Block 1 NOT FOUND")

# Block 2: Add media fields to payload
old_key = "            _key: { remoteJid: jid, id: msg.id?._serialized, fromMe: msg.fromMe || false, participant: msg.author || undefined },"
new_key = """            _key: { remoteJid: jid, id: msg.id?._serialized, fromMe: msg.fromMe || false, participant: msg.author || undefined },
            ...(mediaData ? { media_data: mediaData, media_mimetype: mediaMimetype, media_filename: mediaFilename } : {}),"""

if old_key in content:
    content = content.replace(old_key, new_key, 1)
    print("Block 2 replaced OK")
else:
    print("Block 2 NOT FOUND")

with open(filepath, "w") as f:
    f.write(content)
print("File saved")
