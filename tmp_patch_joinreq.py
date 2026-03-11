#!/usr/bin/env python3
"""Patch integrasi-wa/index.js to forward group_membership_request events to webhook."""
import re
import sys

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    src = f.read()

# The new listener to add after client.on('group_update', ...)
new_listener = r"""    client.on('group_membership_request', async (notification) => {
        try {
            const groupId = notification.chatId;
            const requesterJid = notification.author || '';
            // Strip @suffix to get raw number
            const cleanJid = (j) => j ? j.replace(/@(c\.us|s\.whatsapp\.net|lid|g\.us)$/i, '') : '';
            const requesterNum = cleanJid(requesterJid);
            // Try to get group name from cache
            const groupMeta = sess.groupCache.get(groupId);
            let groupName = groupMeta?.subject || groupId;
            if (!groupMeta) {
                try { const chat = await client.getChatById(groupId); groupName = chat.name || groupId; } catch {}
            }
            const sessObj = sessions.get(id);
            const wUrl = sessObj?.webhookUrl || WEBHOOK_URL;
            const wEnabled = sessObj?.webhookEnabled !== undefined ? sessObj.webhookEnabled : WEBHOOK_ENABLED;
            if (!wUrl || !wEnabled) return;
            const payload = {
                phone_id: id,
                type: 'group_membership_request',
                group_id: groupId,
                group_name: groupName,
                requester: requesterNum,
                requester_jid: requesterJid,
                timestamp: notification.timestamp || Math.floor(Date.now() / 1000),
            };
            console.log('[MembershipRequest][' + id + '] ' + requesterNum + ' -> ' + groupName);
            try {
                const resp = await fetch(wUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                console.log('[MembershipRequest][' + id + '] webhook status: ' + resp.status);
            } catch (e) { console.error('[MembershipRequest][' + id + '] webhook error:', e.message); }
        } catch (e) { console.error('[MembershipRequest][' + id + '] error:', e.message); }
    });
"""

# Insert after the group_update handler block
# Find the pattern: end of group_update handler + try { await client.initialize()
old_anchor = "    client.on('group_update', async (notification) => {\n        try {\n            const chat = await client.getChatById(notification.chatId);\n            if (chat.isGroup) sess.groupCache.set(notification.chatId, { subject: chat.name, participants: chat.participants });\n        } catch { }\n    });"

if old_anchor not in src:
    # Try the compact version (server may have long lines)
    # Find via simpler pattern
    idx = src.find("client.on('group_update'")
    if idx == -1:
        print("ERROR: could not find group_update handler")
        sys.exit(1)
    # Find the closing of that handler: });
    end_idx = src.find('});\n', idx)
    if end_idx == -1:
        print("ERROR: could not find end of group_update handler")
        sys.exit(1)
    end_idx += len('});\n')

    new_src = src[:end_idx] + new_listener + src[end_idx:]
else:
    new_src = src.replace(old_anchor, old_anchor + '\n' + new_listener, 1)

if new_src == src:
    print("ERROR: no change made (pattern not found or already patched)")
    sys.exit(1)

# Check if already patched
if "group_membership_request" in src and "MembershipRequest" in src:
    print("ALREADY PATCHED - skipping")
    sys.exit(0)

with open(path, 'w') as f:
    f.write(new_src)

print("PATCH OK - group_membership_request listener added")
