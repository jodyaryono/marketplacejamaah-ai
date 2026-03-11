#!/usr/bin/env python3
"""
Fix LID JID handling in gateway:
1. Resolve LID numbers to real phone numbers in checkGroupMembershipRequests
2. Accept requester_jid override in approve/reject endpoints
"""
import re
import sys

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    code = f.read()

original = code

# ─── PATCH 1: Resolve LID to real phone in checkGroupMembershipRequests ───────
old1 = '''                const requesterJid = req.id?._serialized || (typeof req.id === 'string' ? req.id : String(req.id));
                const reqPhone = requesterJid.replace(/@(c\\.us|s\\.whatsapp\\.net|lid)$/i, '');
                const cacheKey = `${reqPhone}@${groupJid}`;'''

new1 = '''                const requesterJid = req.id?._serialized || (typeof req.id === 'string' ? req.id : String(req.id));
                const reqPhone = requesterJid.replace(/@(c\\.us|s\\.whatsapp\\.net|lid)$/i, '');
                // Resolve LID to real phone number for DM purposes
                let finalPhone = reqPhone;
                if (requesterJid.endsWith('@lid')) {
                    try {
                        const contact = await sess.client.getContactById(requesterJid);
                        if (contact?.number) finalPhone = String(contact.number).replace(/^\\+/, '');
                    } catch(e) { /* LID not resolvable, keep LID number */ }
                }
                const cacheKey = `${finalPhone}@${groupJid}`;'''

if old1 in code:
    code = code.replace(old1, new1)
    print('[1] ✓ LID resolution added to checkGroupMembershipRequests')
else:
    print('[1] ✗ Could not find checkGroupMembershipRequests LID block')
    sys.exit(1)

# ─── PATCH 2: Update requester field sent to webhook (finalPhone not reqPhone) ─
old2 = '''                            requester: reqPhone,
                            requester_jid: requesterJid,'''
new2 = '''                            requester: finalPhone,
                            requester_jid: requesterJid,'''

if old2 in code:
    code = code.replace(old2, new2)
    print('[2] ✓ finalPhone used as requester in webhook payload')
else:
    print('[2] ✗ Could not find requester/requester_jid in webhook body')
    sys.exit(1)

# ─── PATCH 3: Approve endpoint — accept requester_jid override ────────────────
old3 = '''        const { group_id, requester } = req.body;
        if (!group_id || !requester) return res.status(400).json({ error: 'group_id and requester required' });
        const chat = await s.client.getChatById(group_id);
        if (!chat?.isGroup) return res.status(404).json({ error: 'Group not found: ' + group_id });
        const requesterJid = numberToJid(String(requester).replace(/\\D/g, ''));
        const result = await chat.approveGroupMembershipRequests({ requesterIds: [requesterJid] });
        // Clear from notified cache so re-requests are re-processed
        const notified = notifiedApprovalRequests.get(pid);
        if (notified) notified.delete(`${String(requester).replace(/\\D/g,'')}@${group_id}`);
        console.log('[Approval] Approved ' + requester + ' for group ' + group_id);'''

new3 = '''        const { group_id, requester, requester_jid: reqJidOverride } = req.body;
        if (!group_id || !requester) return res.status(400).json({ error: 'group_id and requester required' });
        const chat = await s.client.getChatById(group_id);
        if (!chat?.isGroup) return res.status(404).json({ error: 'Group not found: ' + group_id });
        const requesterJid = reqJidOverride || numberToJid(String(requester).replace(/\\D/g, ''));
        const result = await chat.approveGroupMembershipRequests({ requesterIds: [requesterJid] });
        // Clear from notified cache so re-requests are re-processed
        const notified = notifiedApprovalRequests.get(pid);
        const cachePhone = reqJidOverride ? reqJidOverride.replace(/@.*$/, '') : String(requester).replace(/\\D/g,'');
        if (notified) notified.delete(`${cachePhone}@${group_id}`);
        console.log('[Approval] Approved ' + (reqJidOverride || requester) + ' for group ' + group_id);'''

if old3 in code:
    code = code.replace(old3, new3)
    print('[3] ✓ approve-membership accepts requester_jid override')
else:
    print('[3] ✗ Could not find approve-membership body')
    sys.exit(1)

# ─── PATCH 4: Reject endpoint — accept requester_jid override ─────────────────
old4 = '''        const { group_id, requester } = req.body;
        if (!group_id || !requester) return res.status(400).json({ error: 'group_id and requester required' });
        const chat = await s.client.getChatById(group_id);
        if (!chat?.isGroup) return res.status(404).json({ error: 'Group not found: ' + group_id });
        const requesterJid = numberToJid(String(requester).replace(/\\D/g, ''));
        const result = await chat.rejectGroupMembershipRequests({ requesterIds: [requesterJid] });
        console.log('[Approval] Rejected ' + requester + ' for group ' + group_id);'''

new4 = '''        const { group_id, requester, requester_jid: reqJidOverride } = req.body;
        if (!group_id || !requester) return res.status(400).json({ error: 'group_id and requester required' });
        const chat = await s.client.getChatById(group_id);
        if (!chat?.isGroup) return res.status(404).json({ error: 'Group not found: ' + group_id });
        const requesterJid = reqJidOverride || numberToJid(String(requester).replace(/\\D/g, ''));
        const result = await chat.rejectGroupMembershipRequests({ requesterIds: [requesterJid] });
        console.log('[Approval] Rejected ' + (reqJidOverride || requester) + ' for group ' + group_id);'''

if old4 in code:
    code = code.replace(old4, new4)
    print('[4] ✓ reject-membership accepts requester_jid override')
else:
    print('[4] ✗ Could not find reject-membership body')
    sys.exit(1)

if code == original:
    print('No changes made!')
    sys.exit(1)

with open(path, 'w') as f:
    f.write(code)

print('DONE')
