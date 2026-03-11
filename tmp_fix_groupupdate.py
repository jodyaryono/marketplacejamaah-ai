#!/usr/bin/env python3
"""Fix broken group_update handler in index.js."""

import re
path = '/var/www/integrasi-wa.jodyaryono.id/index.js'

with open(path, 'r', encoding='utf-8') as f:
    src = f.read()

# Replace the broken handler with the correct version
broken = """    client.on('group_update', async (notification) => {
        try {
            const chat = await client.getChatById(notification.chatId);
            if (chat.isGroup) sess.groupCache.set(notification.chatId, { subject: chat.name, participants: chat.participants
        // Trigger membership request check whenever group state changes
        try { await checkGroupMembershipRequests(id); } catch { }
    });
        } catch { }
    });"""

# Try to normalize whitespace discrepancies

# Find the broken group_update block
pattern = r"client\.on\('group_update',\s*async.*?participants.*?}\s*\)\s*;.*?}\s*\)\s*;"
matches = list(re.finditer(pattern, src, re.DOTALL))
print(f"Found {len(matches)} group_update blocks")

if matches:
    found = matches[0].group(0)
    print("Found block:", repr(found[:200]))

    fixed = """client.on('group_update', async (notification) => {
        try {
            const chat = await client.getChatById(notification.chatId);
            if (chat.isGroup) sess.groupCache.set(notification.chatId, { subject: chat.name, participants: chat.participants });
        } catch { }
        // Trigger membership request check whenever group state changes
        try { await checkGroupMembershipRequests(id); } catch { }
    });"""

    src = src[:matches[0].start()] + fixed + src[matches[0].end():]
    print('Fixed group_update handler ✓')
else:
    print('FAILED - group_update block not found')

with open(path, 'w', encoding='utf-8') as f:
    f.write(src)

print('DONE')
