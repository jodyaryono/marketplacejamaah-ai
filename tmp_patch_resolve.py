import re

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    code = f.read()

# Add /api/resolve-number endpoint right before the /api/send-image line
anchor = "app.post('/api/send-image',"
if anchor not in code:
    print("ERROR: anchor not found")
    exit(1)

endpoint_code = """app.get('/api/resolve-number', apiAuth, async (req, res) => {
    try {
        const { lid, phone_id } = req.query;
        if (!lid) return res.status(400).json({ error: 'lid required' });
        const pid = sanitizeId(phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected' });
        const jid = lid.includes('@') ? lid : lid + '@lid';
        const contact = await s.client.getContactById(jid);
        const realNum = contact?.number ? String(contact.number).replace(/\\D/g, '') : null;
        res.json({ success: true, lid: lid, phone_number: realNum, pushname: contact?.pushname || null, name: contact?.name || null });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});
"""

code = code.replace(anchor, endpoint_code + anchor)
print("Patch applied: /api/resolve-number endpoint added")

with open(path, 'w') as f:
    f.write(code)
print("File written")
