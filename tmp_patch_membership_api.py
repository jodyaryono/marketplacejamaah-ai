#!/usr/bin/env python3
"""Patch integrasi-wa/index.js to add /approve-membership and /reject-membership endpoints."""
import sys

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    src = f.read()

if '/approve-membership' in src:
    print("ALREADY PATCHED")
    sys.exit(0)

new_endpoints = r"""
app.post('/api/approve-membership', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const groupId = req.body.group_id;
        if (!groupId) return res.status(400).json({ error: 'group_id required' });
        const requesterJid = req.body.requester_jid || (req.body.requester ? req.body.requester + '@c.us' : null);
        if (!requesterJid) return res.status(400).json({ error: 'requester or requester_jid required' });
        const chat = await s.client.getChatById(groupId);
        const opts = { requesterIds: [requesterJid] };
        const result = await chat.approveGroupMembershipRequests(opts);
        console.log('[ApproveMembership] ' + requesterJid + ' -> ' + groupId, result);
        res.json({ success: true, phone_id: pid, group_id: groupId, requester_jid: requesterJid, result });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});

app.post('/api/reject-membership', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const groupId = req.body.group_id;
        if (!groupId) return res.status(400).json({ error: 'group_id required' });
        const requesterJid = req.body.requester_jid || (req.body.requester ? req.body.requester + '@c.us' : null);
        if (!requesterJid) return res.status(400).json({ error: 'requester or requester_jid required' });
        const chat = await s.client.getChatById(groupId);
        const opts = { requesterIds: [requesterJid] };
        const result = await chat.rejectGroupMembershipRequests(opts);
        console.log('[RejectMembership] ' + requesterJid + ' -> ' + groupId, result);
        res.json({ success: true, phone_id: pid, group_id: groupId, requester_jid: requesterJid, result });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});

"""

# Insert before the join-group endpoint (which we added earlier), or before app.listen
# Find the /api/join-group endpoint as insertion point
anchor = "app.post('/api/join-group',"
idx = src.find(anchor)
if idx == -1:
    # Fallback: insert before the last few lines (app.listen or similar)
    anchor = "app.listen("
    idx = src.find(anchor)
    if idx == -1:
        print("ERROR: could not find insertion point")
        sys.exit(1)

new_src = src[:idx] + new_endpoints + src[idx:]

with open(path, 'w') as f:
    f.write(new_src)

print("PATCH OK - approve/reject membership endpoints added")
