#!/usr/bin/env python3
"""
Comprehensive LID fix for the WhatsApp gateway (integrasi-wa).
Patches:
1. numberToJid() — use @lid suffix for 14+ digit numbers
2. forwardToWebhook() — use contact.number to resolve LID, send sender_lid field
"""
import re

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    code = f.read()

# ═══════════════════════════════════════════════════════════════════════
# PATCH 1: numberToJid — route 14+ digit LID numbers to @lid instead of @c.us
# ═══════════════════════════════════════════════════════════════════════
old_ntj = """function numberToJid(number) {
    let num = String(number).replace(/[\\s\\-\\(\\)]/g, '');
    if (num.startsWith('+')) num = num.slice(1);
    num = num.replace(/\\D/g, '');
    if (num.startsWith('0')) num = '62' + num.slice(1);
    else if (num.startsWith('8') && num.length >= 9 && num.length <= 13) num = '62' + num;
    return num + '@c.us';
}"""

new_ntj = """function numberToJid(number) {
    let num = String(number).replace(/[\\s\\-\\(\\)]/g, '');
    if (num.startsWith('+')) num = num.slice(1);
    num = num.replace(/\\D/g, '');
    if (num.startsWith('0')) num = '62' + num.slice(1);
    else if (num.startsWith('8') && num.length >= 9 && num.length <= 13) num = '62' + num;
    // LID numbers are 14+ digits — route to @lid instead of @c.us
    if (num.length >= 14) return num + '@lid';
    return num + '@c.us';
}"""

if old_ntj in code:
    code = code.replace(old_ntj, new_ntj, 1)
    print('PATCH 1 OK: numberToJid now handles LID numbers with @lid suffix')
elif 'num + \'@lid\'' in code:
    print('PATCH 1 SKIP: numberToJid already patched')
else:
    # Try regex approach
    m = re.search(r'function numberToJid\(number\)\s*\{[^}]+\}', code)
    if m and '@lid' not in m.group(0):
        old_fn = m.group(0)
        new_fn = old_fn.replace(
            "return num + '@c.us';",
            "// LID numbers are 14+ digits — route to @lid instead of @c.us\n    if (num.length >= 14) return num + '@lid';\n    return num + '@c.us';",
            1
        )
        if new_fn != old_fn:
            code = code.replace(old_fn, new_fn, 1)
            print('PATCH 1 OK (regex): numberToJid patched')
        else:
            print('PATCH 1 FAIL: could not patch numberToJid')
    else:
        print('PATCH 1 SKIP: already patched or not found')

# ═══════════════════════════════════════════════════════════════════════
# PATCH 2: forwardToWebhook — resolve LID via contact.number + add sender_lid
# ═══════════════════════════════════════════════════════════════════════
# The key change: when we get the contact, use contact.number (the REAL phone)
# and track if the original was a LID

# Find the current sender/payload section
old_payload_section = """    try {
        const contact = await msg.getContact();
        const pushName = contact?.pushname || null;
        const payload = {
            phone_id: phoneId, message_id: msg.id?._serialized, message: textContent,
            type: contentType?.replace('Message', '') || 'text',
            timestamp: msg.timestamp || Math.floor(Date.now() / 1000),
            sender: fromNum, sender_name: pushName, from: jid.replace('@c.us', '').replace('@g.us', ''),
            pushname: pushName,"""

new_payload_section = """    try {
        const contact = await msg.getContact();
        const pushName = contact?.pushname || null;
        // Resolve LID → real phone via contact.number (WhatsApp knows the mapping)
        let senderPhone = fromNum;
        let senderLid = null;
        const authorJid = isGroup ? (msg.author || '') : (jid || '');
        if (authorJid.endsWith('@lid') || (fromNum.length >= 14 && /^\\d+$/.test(fromNum))) {
            senderLid = fromNum;
            if (contact?.number) {
                let realNum = String(contact.number).replace(/\\D/g, '');
                if (realNum.startsWith('0')) realNum = '62' + realNum.slice(1);
                if (realNum.length >= 10 && realNum.length <= 15) {
                    senderPhone = realNum;
                    console.log('[LID->Phone] Resolved ' + senderLid + ' -> ' + senderPhone);
                }
            } else {
                console.warn('[LID->Phone] contact.number unavailable for LID ' + senderLid);
            }
        } else if (contact?.number) {
            // Even for non-LID, prefer contact.number when available (more reliable)
            let realNum = String(contact.number).replace(/\\D/g, '');
            if (realNum.startsWith('0')) realNum = '62' + realNum.slice(1);
            if (realNum.length >= 10 && realNum.length <= 15) {
                senderPhone = realNum;
            }
        }
        const payload = {
            phone_id: phoneId, message_id: msg.id?._serialized, message: textContent,
            type: contentType?.replace('Message', '') || 'text',
            timestamp: msg.timestamp || Math.floor(Date.now() / 1000),
            sender: senderPhone, sender_name: pushName, from: jid.replace('@c.us', '').replace('@g.us', ''),
            pushname: pushName,
            ...(senderLid ? { sender_lid: senderLid } : {}),"""

if old_payload_section in code:
    code = code.replace(old_payload_section, new_payload_section, 1)
    print('PATCH 2 OK: forwardToWebhook now resolves LID via contact.number')
else:
    # Try to find the pattern with different whitespace
    idx = code.find('sender: fromNum, sender_name: pushName')
    if idx > 0 and 'senderLid' not in code:
        # Replace just the sender line
        code = code.replace(
            'sender: fromNum, sender_name: pushName, from: jid.replace(\'@c.us\', \'\').replace(\'@g.us\', \'\'),',
            'sender: senderPhone, sender_name: pushName, from: jid.replace(\'@c.us\', \'\').replace(\'@g.us\', \'\'),\n            ...(senderLid ? { sender_lid: senderLid } : {}),',
            1
        )
        # Also inject the LID resolution block before the payload
        code = code.replace(
            '        const contact = await msg.getContact();\n        const pushName = contact?.pushname || null;\n        const payload = {',
            """        const contact = await msg.getContact();
        const pushName = contact?.pushname || null;
        // Resolve LID → real phone via contact.number
        let senderPhone = fromNum;
        let senderLid = null;
        const authorJid = isGroup ? (msg.author || '') : (jid || '');
        if (authorJid.endsWith('@lid') || (fromNum.length >= 14 && /^\\d+$/.test(fromNum))) {
            senderLid = fromNum;
            if (contact?.number) {
                let realNum = String(contact.number).replace(/\\D/g, '');
                if (realNum.startsWith('0')) realNum = '62' + realNum.slice(1);
                if (realNum.length >= 10 && realNum.length <= 15) {
                    senderPhone = realNum;
                    console.log('[LID->Phone] Resolved ' + senderLid + ' -> ' + senderPhone);
                }
            } else {
                console.warn('[LID->Phone] contact.number unavailable for LID ' + senderLid);
            }
        } else if (contact?.number) {
            let realNum = String(contact.number).replace(/\\D/g, '');
            if (realNum.startsWith('0')) realNum = '62' + realNum.slice(1);
            if (realNum.length >= 10 && realNum.length <= 15) {
                senderPhone = realNum;
            }
        }
        const payload = {""",
            1
        )
        print('PATCH 2 OK (fallback): forwardToWebhook patched')
    elif 'senderLid' in code:
        print('PATCH 2 SKIP: already patched')
    else:
        print('PATCH 2 FAIL: could not find payload section')
        # Debug: show context
        idx2 = code.find('forwardToWebhook')
        if idx2 > 0:
            print('Context around forwardToWebhook:')
            snippet = code[idx2:idx2+500]
            print(repr(snippet[:200]))

# ═══════════════════════════════════════════════════════════════════════
# PATCH 3: Update the webhook log line to show resolved phone
# ═══════════════════════════════════════════════════════════════════════
old_log = "console.log('[Webhook][' + phoneId + '] ' + resp.status);"
new_log = "console.log('[Webhook][' + phoneId + '] ' + resp.status + ' sender=' + senderPhone + (senderLid ? ' (LID:' + senderLid + ')' : ''));"

if old_log in code:
    code = code.replace(old_log, new_log, 1)
    print('PATCH 3 OK: webhook log now shows resolved phone')
elif 'senderPhone' in code.split('console.log(\'[Webhook]')[1][:100] if 'console.log(\'[Webhook]' in code else '':
    print('PATCH 3 SKIP: already patched')
else:
    print('PATCH 3 SKIP: log line not found')

with open(path, 'w') as f:
    f.write(code)
print('\nAll patches written to ' + path)
