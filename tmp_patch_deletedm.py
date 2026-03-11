
marker = "app.post('/api/delete', apiAuth,"

new_endpoint = r"""
app.post('/api/delete-last-dm', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { phone_number, count } = req.body;
        if (!phone_number) return res.status(400).json({ error: 'phone_number required' });
        const jid = numberToJid(String(phone_number).replace(/\D+/g, '') + '@c.us');
        const chat = await s.client.getChatById(jid);
        const msgs = await chat.fetchMessages({ limit: 30 });
        const botMsgs = msgs.filter(m => m.fromMe === true);
        const toDelete = botMsgs.slice(-(count || 1));
        let deleted = 0;
        for (const m of toDelete) { try { await m.delete(true); deleted++; } catch(e2) {} }
        res.json({ success: true, deleted, phone_number });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});

"""

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'

with open(path, 'r') as f:
    content = f.read()

if '/api/delete-last-dm' in content:
    print('ALREADY PATCHED')
else:
    content = content.replace(marker, new_endpoint + marker, 1)
    with open(path, 'w') as f:
        f.write(content)
    print('PATCHED OK')
