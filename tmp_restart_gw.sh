#!/bin/bash
# Add replay-group endpoint and restart gateway with proper logging
set -e
cd /var/www/integrasi-wa.jodyaryono.id

# Backup
cp index.js index.js.bak_replay

# Add the replay-group endpoint after refresh-groups endpoint
# Find the line number of the PROACTIVE MONITORING comment
LINE=$(grep -n 'PROACTIVE MONITORING AGENT' index.js | head -1 | cut -d: -f1)
echo "Inserting replay endpoint before line $LINE"

# Create the endpoint code
cat > /tmp/replay_endpoint.js << 'ENDPOINT'
app.post('/api/replay-group', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected' });
        const { group_id, limit: msgLimit, sender_filter } = req.body;
        if (!group_id) return res.status(400).json({ error: 'group_id required' });
        const jid = resolveGroupJid(group_id, s.groupCache);
        if (!jid) return res.status(404).json({ error: 'Group not found: ' + group_id });
        const chat = await s.client.getChatById(jid);
        const msgs = await chat.fetchMessages({ limit: parseInt(msgLimit) || 20 });
        let replayed = 0, skipped = 0;
        for (const msg of msgs) {
            if (msg.fromMe) { skipped++; continue; }
            // Optional sender filter
            if (sender_filter) {
                let fromNum;
                try { const c = await msg.getContact(); fromNum = c?.number || ''; } catch { fromNum = ''; }
                if (!fromNum.includes(sender_filter)) { skipped++; continue; }
            }
            await forwardToWebhook(msg, pid, s.groupCache);
            replayed++;
        }
        res.json({ success: true, phone_id: pid, group: jid, fetched: msgs.length, replayed, skipped });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});

ENDPOINT

# Insert the endpoint code before the PROACTIVE MONITORING line
sed -i "$((LINE-1))r /tmp/replay_endpoint.js" index.js

echo "Endpoint added. Restarting gateway..."

# Kill old process
pkill -f 'node.*index.js' || true
sleep 3

# Verify killed
if pgrep -f 'node.*index.js' > /dev/null 2>&1; then
    echo "Process still alive, force killing..."
    pkill -9 -f 'node.*index.js' || true
    sleep 2
fi

# Start with proper logging
nohup node index.js >> /tmp/wa_gateway.log 2>&1 &
NEW_PID=$!
echo "Gateway started with PID: $NEW_PID"
echo "Waiting for startup..."
sleep 10

# Check status
curl -s http://localhost:3001/api/status -H "Authorization: Bearer fc42fe461f106cdee387e807b972b52b" 2>&1
echo ""
echo "Done."
