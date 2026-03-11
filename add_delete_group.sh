#!/bin/bash
cd /var/www/integrasi-wa.jodyaryono.id

# Add delete-group endpoint before refresh-groups
sed -i '/^app\.post.*\/api\/refresh-groups/i\
app.post('"'"'/api/delete-group'"'"', apiAuth, async (req, res) => {\
    try {\
        const pid = sanitizeId(req.body.phone_id || '"'"''"'"') || getFirstOpenSession();\
        const s = sessions.get(pid);\
        if (!s || s.status !== '"'"'open'"'"') return res.status(503).json({ error: '"'"'Session not connected'"'"', phone_id: pid });\
        const { group_id } = req.body;\
        if (!group_id) return res.status(400).json({ error: '"'"'group_id required'"'"' });\
        const jid = resolveGroupJid(group_id, s.groupCache);\
        if (!jid) return res.status(404).json({ error: '"'"'Group not found: '"'"' + group_id });\
        const chat = await s.client.getChatById(jid);\
        if (!chat.isGroup) return res.status(400).json({ error: '"'"'Not a group'"'"' });\
        const nonMe = chat.participants.filter(p => !p.id._serialized.startsWith(s.client.info.wid._serialized.split('"'"'@'"'"')[0]));\
        if (nonMe.length > 0) await chat.removeParticipants(nonMe.map(p => p.id._serialized));\
        await chat.leave();\
        s.groupCache.delete(jid);\
        res.json({ success: true, removed_members: nonMe.length, left: true });\
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }\
});' index.js

echo "=== Verify ==="
grep -n 'delete-group' index.js

echo "=== Restart ==="
supervisorctl restart integrasi-wa
sleep 10
tail -5 gateway.log
