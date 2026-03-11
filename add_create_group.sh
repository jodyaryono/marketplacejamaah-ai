#!/bin/bash
cd /var/www/integrasi-wa.jodyaryono.id

# Add create-group endpoint before the refresh-groups endpoint
sed -i '/^app\.post.*\/api\/refresh-groups/i\
app.post('"'"'/api/create-group'"'"', apiAuth, async (req, res) => {\
    try {\
        const pid = sanitizeId(req.body.phone_id || '"'"''"'"') || getFirstOpenSession();\
        const s = sessions.get(pid);\
        if (!s || s.status !== '"'"'open'"'"') return res.status(503).json({ error: '"'"'Session not connected'"'"', phone_id: pid });\
        const { name, members } = req.body;\
        if (!name) return res.status(400).json({ error: '"'"'name required'"'"' });\
        const participants = (members || []).map(m => numberToJid(m));\
        const result = await s.client.createGroup(name, participants);\
        await refreshGroupCacheForSession(pid);\
        res.json({ success: true, phone_id: pid, data: { gid: result.gid, title: result.title || name } });\
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }\
});' index.js

echo "=== Verify ==="
grep -n 'create-group' index.js

echo "=== Restart ==="
supervisorctl restart integrasi-wa
sleep 10
tail -5 gateway.log
