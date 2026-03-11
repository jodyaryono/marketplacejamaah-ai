path = "/var/www/integrasi-wa.jodyaryono.id/index.js"
with open(path, "r") as f:
    content = f.read()

reaction_handler = """    client.on('message_reaction', async (reaction) => {
        if (!reaction) return;
        try {
            const settings = db.get('sessions').find({ id }).value();
            if (!settings || !settings.webhook_enabled || !settings.webhook_url) return;
            const wUrl = settings.webhook_url;
            const jid      = reaction.msgId?.remote || '';
            const isGroup  = jid.endsWith('@g.us');
            const fromJid  = reaction.senderId || '';
            const fromNum  = fromJid.replace(/@\\S+/g, '');
            let senderNum  = fromNum;
            try {
                const contact = await reaction.getContact?.();
                if (contact?.id?.user && /^\\d{10,13}$/.test(contact.id.user)) senderNum = contact.id.user;
            } catch {}
            const payload = {
                phone_id:          id,
                type:              'reaction',
                emoji:             reaction.reaction || null,
                target_message_id: reaction.msgId?._serialized || null,
                sender:            senderNum,
                sender_name:       null,
                timestamp:         Math.floor(Date.now() / 1000),
                ...(isGroup ? { group_id: jid, from_group: jid, group_name: sess.groupCache.get(jid)?.subject || jid } : {}),
            };
            await fetch(wUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        } catch (e) { console.error('[Reaction][' + id + ']', e.message); }
    });
"""

target = "    client.on('group_update',"
if "message_reaction" in content:
    print("Already patched!")
elif target in content:
    content = content.replace(target, reaction_handler + target, 1)
    with open(path, "w") as f:
        f.write(content)
    print("Patched successfully!")
else:
    print("ERROR: target not found")
