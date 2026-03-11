#!/bin/bash
cd /var/www/integrasi-wa.jodyaryono.id

# Add add-member endpoint before refresh-groups
sed -i '/^app\.post.*\/api\/refresh-groups/i\
app.post('"'"'/api/add-member'"'"', apiAuth, async (req, res) => {\
    try {\
        const pid = sanitizeId(req.body.phone_id || '"'"''"'"') || getFirstOpenSession();\
        const s = sessions.get(pid);\
        if (!s || s.status !== '"'"'open'"'"') return res.status(503).json({ error: '"'"'Session not connected'"'"', phone_id: pid });\
        const { group_id, members } = req.body;\
        if (!group_id || !members || !members.length) return res.status(400).json({ error: '"'"'group_id and members required'"'"' });\
        const jid = resolveGroupJid(group_id, s.groupCache);\
        if (!jid) return res.status(404).json({ error: '"'"'Group not found: '"'"' + group_id });\
        const chat = await s.client.getChatById(jid);\
        const participants = members.map(m => numberToJid(m));\
        const result = await chat.addParticipants(participants);\
        await refreshGroupCacheForSession(pid);\
        res.json({ success: true, phone_id: pid, data: result });\
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }\
});' index.js

echo "=== Verify ==="
grep -n 'add-member' index.js

echo "=== Restart ==="
supervisorctl restart integrasi-wa
sleep 10
tail -5 gateway.log
