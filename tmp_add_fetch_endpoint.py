import sys, re

filepath = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(filepath, 'r') as f:
    code = f.read()

# Insert BEFORE the monitoring section
anchor = "// ─── PROACTIVE MONITORING AGENT"

new_endpoint = """
// ─── FETCH RECENT MESSAGES (recovery after crash) ────────────────────────────
app.get('/api/fetch-messages', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.query.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected' });
        const chatId = (req.query.chat_id || '').trim();
        if (!chatId) return res.status(400).json({ error: 'chat_id is required (e.g. 628xxx@c.us or xxx@g.us)' });
        const limit = Math.min(parseInt(req.query.limit) || 20, 50);
        const chat = await s.client.getChatById(chatId);
        const msgs = await chat.fetchMessages({ limit });
        const result = msgs.filter(m => !m.fromMe).map(m => ({
            message_id: m.id._serialized,
            sender: (m.author || m.from || '').replace(/@\\S+/, ''),
            sender_name: m._data?.notifyName || '',
            message: m.body || null,
            type: m.type || 'conversation',
            timestamp: m.timestamp,
            has_media: m.hasMedia || false,
            from_me: m.fromMe,
            from: m.from,
            group_id: m.from?.includes('@g.us') ? m.from : null,
        }));
        res.json({ phone_id: pid, chat_id: chatId, total: result.length, messages: result });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});

"""

if anchor in code:
    code = code.replace(anchor, new_endpoint + anchor)
    with open(filepath, 'w') as f:
        f.write(code)
    print('OK: fetch-messages endpoint added')
else:
    print('ERROR: anchor not found')
