#!/usr/bin/env python3
"""Patch index.js to add group membership approval flow."""

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'

with open(path, 'r', encoding='utf-8') as f:
    src = f.read()

# ── 1. Add notifiedApprovalRequests map after sessions Map ──────────────────
old1 = "// ─── MULTI-SESSION STATE ─────────────────────────────────────────────────────\n─                                                                               const sessions = new Map();"
# Try the actual text
old1 = 'const sessions = new Map();'
new1 = '''const sessions = new Map();
const notifiedApprovalRequests = new Map(); // phoneId -> Set<'requester@groupJid'>'''

if old1 in src:
    src = src.replace(old1, new1, 1)
    print('[1] Added notifiedApprovalRequests map ✓')
else:
    print('[1] FAILED - could not find sessions Map definition')

# ── 2. Add checkGroupMembershipRequests function after refreshGroupCacheForSession ──
old2 = 'async function removeSession(phoneId) {'
new2 = '''async function checkGroupMembershipRequests(phoneId) {
    const sess = sessions.get(phoneId);
    if (!sess?.client || sess.status !== 'open') return;
    const wUrl = sess?.webhookUrl || WEBHOOK_URL;
    const wEnabled = sess?.webhookEnabled !== undefined ? sess.webhookEnabled : WEBHOOK_ENABLED;
    if (!wUrl || !wEnabled) return;
    if (!notifiedApprovalRequests.has(phoneId)) notifiedApprovalRequests.set(phoneId, new Set());
    const notified = notifiedApprovalRequests.get(phoneId);
    for (const [groupJid, groupMeta] of sess.groupCache) {
        try {
            const requests = await sess.client.getGroupMembershipRequests(groupJid);
            if (!requests?.length) continue;
            for (const req of requests) {
                const requesterJid = req.id?._serialized || (typeof req.id === 'string' ? req.id : String(req.id));
                const reqPhone = requesterJid.replace(/@(c\\.us|s\\.whatsapp\\.net|lid)$/i, '');
                const cacheKey = `${reqPhone}@${groupJid}`;
                if (notified.has(cacheKey)) continue;
                notified.add(cacheKey);
                try {
                    await fetch(wUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            phone_id: phoneId,
                            type: 'group_membership_request',
                            group_id: groupJid,
                            group_name: groupMeta.subject || groupJid,
                            requester: reqPhone,
                            requester_jid: requesterJid,
                            method: req.requestMethod || 'InviteLink',
                            timestamp: req.t || Math.floor(Date.now() / 1000),
                        })
                    });
                    console.log('[Approval] Join request from ' + reqPhone + ' for group ' + (groupMeta.subject || groupJid));
                } catch (e) {
                    console.error('[Approval] Webhook error:', e.message);
                    notified.delete(cacheKey);
                }
            }
        } catch { }
    }
}

async function removeSession(phoneId) {'''

if 'async function removeSession(phoneId) {' in src:
    src = src.replace('async function removeSession(phoneId) {', new2, 1)
    print('[2] Added checkGroupMembershipRequests function ✓')
else:
    print('[2] FAILED - could not find removeSession function')

# ── 3. Extend group_update handler to trigger approval check ──────────────
old3 = "    client.on('group_update', async (notification) => {\n        try {\n            const chat = await client.getChatById(notification.chatId);\n            if (chat.isGroup) sess.groupCache.set(notification.chatId, { subject: chat.name, participants: chat.participants });\n        } catch { }\n    });"
new3 = """    client.on('group_update', async (notification) => {
        try {
            const chat = await client.getChatById(notification.chatId);
            if (chat.isGroup) sess.groupCache.set(notification.chatId, { subject: chat.name, participants: chat.participants });
        } catch { }
        // Trigger membership request check whenever group state changes
        try { await checkGroupMembershipRequests(id); } catch { }
    });"""

if "client.on('group_update'" in src and "try { await checkGroupMembershipRequests" not in src:
    # Find the group_update handler and extend it
    import re
    pattern = r"client\.on\('group_update'.*?}\);"
    match = re.search(pattern, src, re.DOTALL)
    if match:
        old_handler = match.group(0)
        new_handler = old_handler.rstrip(');').rstrip('}').rstrip(
        ) + "\n        // Trigger membership request check whenever group state changes\n        try { await checkGroupMembershipRequests(id); } catch { }\n    });"
        src = src.replace(old_handler, new_handler, 1)
        print('[3] Extended group_update handler ✓')
    else:
        print('[3] FAILED - regex did not match group_update handler')
else:
    print('[3] SKIPPED - already patched or not found')

# ── 4. Add approve-membership and reject-membership API endpoints before app.listen ──
old4 = "// ─── START ───────────────────────────────────────────────────────────────────"
new4 = """// ─── GROUP MEMBERSHIP APPROVAL ─────────────────────────────────────────────────
app.post('/api/approve-membership', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { group_id, requester } = req.body;
        if (!group_id || !requester) return res.status(400).json({ error: 'group_id and requester required' });
        const chat = await s.client.getChatById(group_id);
        if (!chat?.isGroup) return res.status(404).json({ error: 'Group not found: ' + group_id });
        const requesterJid = numberToJid(String(requester).replace(/\\D/g, ''));
        const result = await chat.approveGroupMembershipRequests({ requesterIds: [requesterJid] });
        // Clear from notified cache so re-requests are re-processed
        const notified = notifiedApprovalRequests.get(pid);
        if (notified) notified.delete(`${String(requester).replace(/\\D/g,'')}@${group_id}`);
        console.log('[Approval] Approved ' + requester + ' for group ' + group_id);
        res.json({ success: true, phone_id: pid, result });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});

app.post('/api/reject-membership', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { group_id, requester } = req.body;
        if (!group_id || !requester) return res.status(400).json({ error: 'group_id and requester required' });
        const chat = await s.client.getChatById(group_id);
        if (!chat?.isGroup) return res.status(404).json({ error: 'Group not found: ' + group_id });
        const requesterJid = numberToJid(String(requester).replace(/\\D/g, ''));
        const result = await chat.rejectGroupMembershipRequests({ requesterIds: [requesterJid] });
        console.log('[Approval] Rejected ' + requester + ' for group ' + group_id);
        res.json({ success: true, phone_id: pid, result });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});

// ─── START ───────────────────────────────────────────────────────────────────"""

if "─── START ───" in src and '/api/approve-membership' not in src:
    src = src.replace(old4, new4, 1)
    print('[4] Added approve/reject membership endpoints ✓')
else:
    print('[4] FAILED - start marker not found or already patched')

# ── 5. Add periodic polling inside main() before await loadSessionsFromDb ──
old5 = '    await loadSessionsFromDb();'
new5 = '''    // Poll every 2 minutes for pending group membership requests across all sessions
    setInterval(async () => {
        for (const [phoneId] of sessions) {
            try { await checkGroupMembershipRequests(phoneId); } catch { }
        }
    }, 120000);
    await loadSessionsFromDb();'''

if '    await loadSessionsFromDb();' in src and 'checkGroupMembershipRequests(phoneId)' not in src.split('await loadSessionsFromDb();')[0].split('setInterval')[-1]:
    src = src.replace('    await loadSessionsFromDb();', new5, 1)
    print('[5] Added periodic polling ✓')
else:
    print('[5] SKIPPED - already applied or not found')

with open(path, 'w', encoding='utf-8') as f:
    f.write(src)

print('DONE')
