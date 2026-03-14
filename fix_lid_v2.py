#!/usr/bin/env python3
"""
Comprehensive LID fix v2 for the WhatsApp gateway.
Uses regex-based patching for robustness against whitespace/formatting differences.
"""
import re, sys

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    code = f.read()

changes = 0

# ═══════════════════════════════════════════════════════════════════════
# PATCH 1: numberToJid — route 14+ digit LID numbers to @lid
# ═══════════════════════════════════════════════════════════════════════
if "@lid'" not in code.split('function numberToJid')[1].split('}')[0] if 'function numberToJid' in code else '':
    m = re.search(r"(function numberToJid\(number\)\s*\{.*?)(return\s+num\s*\+\s*['\"]@c\.us['\"];)", code, re.DOTALL)
    if m:
        old = m.group(0)
        new = m.group(1) + "// LID numbers are 14+ digits - route to @lid instead of @c.us\n    if (num.length >= 14) return num + '@lid';\n    " + m.group(2)
        code = code.replace(old, new, 1)
        changes += 1
        print('PATCH 1 OK: numberToJid now handles LID -> @lid')
    else:
        print('PATCH 1 FAIL: numberToJid pattern not found')
        sys.exit(1)
else:
    print('PATCH 1 SKIP: already patched')

# ═══════════════════════════════════════════════════════════════════════
# PATCH 2: forwardToWebhook — resolve LID via contact.number
# ═══════════════════════════════════════════════════════════════════════
if 'senderLid' not in code:
    # Find the section: "const contact = await msg.getContact();" ... "sender: fromNum,"
    # and inject LID resolution between contact fetch and payload construction

    # Step 2a: Inject LID resolution block after contact fetch
    contact_line = "const contact = await msg.getContact();"
    idx = code.find(contact_line)
    if idx < 0:
        print('PATCH 2 FAIL: contact fetch line not found')
        sys.exit(1)

    # Find next "const payload = {" after this point
    payload_idx = code.find('const payload = {', idx)
    if payload_idx < 0:
        print('PATCH 2 FAIL: payload construction not found')
        sys.exit(1)

    # Get the indentation (spaces before "const payload")
    line_start = code.rfind('\n', 0, payload_idx) + 1
    indent = ''
    for ch in code[line_start:payload_idx]:
        if ch in ' \t':
            indent += ch
        else:
            break

    lid_block = f"""{indent}// Resolve LID -> real phone via contact.number (WhatsApp knows the mapping)
{indent}let senderPhone = fromNum;
{indent}let senderLid = null;
{indent}const authorJid = isGroup ? (msg.author || '') : (jid || '');
{indent}if (authorJid.endsWith('@lid') || (fromNum.length >= 14 && /^\\d+$/.test(fromNum))) {{
{indent}    senderLid = fromNum;
{indent}    if (contact && contact.number) {{
{indent}        let realNum = String(contact.number).replace(/\\D/g, '');
{indent}        if (realNum.startsWith('0')) realNum = '62' + realNum.slice(1);
{indent}        if (realNum.length >= 10 && realNum.length <= 15) {{
{indent}            senderPhone = realNum;
{indent}            console.log('[LID->Phone] Resolved ' + senderLid + ' -> ' + senderPhone);
{indent}        }}
{indent}    }} else {{
{indent}        console.warn('[LID->Phone] contact.number unavailable for LID ' + senderLid);
{indent}    }}
{indent}}} else if (contact && contact.number) {{
{indent}    // Even for non-LID, prefer contact.number when available (more reliable)
{indent}    let realNum = String(contact.number).replace(/\\D/g, '');
{indent}    if (realNum.startsWith('0')) realNum = '62' + realNum.slice(1);
{indent}    if (realNum.length >= 10 && realNum.length <= 15) {{
{indent}        senderPhone = realNum;
{indent}    }}
{indent}}}
"""
    # Insert LID block before "const payload = {"
    code = code[:payload_idx] + lid_block + code[payload_idx:]
    changes += 1
    print('PATCH 2a OK: LID resolution block injected')

    # Step 2b: Replace "sender: fromNum" with "sender: senderPhone" in payload
    # Find it within forwardToWebhook function
    fwd_start = code.find('async function forwardToWebhook')
    fwd_end = code.find('\n}\n', code.find('const payload = {', fwd_start))
    if fwd_start >= 0 and fwd_end >= 0:
        fwd_section = code[fwd_start:fwd_end+3]
        new_fwd = fwd_section.replace('sender: fromNum,', 'sender: senderPhone,', 1)
        if new_fwd != fwd_section:
            code = code[:fwd_start] + new_fwd + code[fwd_end+3:]
            changes += 1
            print('PATCH 2b OK: sender field now uses senderPhone')
        else:
            print('PATCH 2b FAIL: sender: fromNum not found in payload')

    # Step 2c: Add sender_lid to payload (after _key line)
    key_line_pattern = r"(_key:\s*\{[^}]+\},)"
    m_key = re.search(key_line_pattern, code[code.find('async function forwardToWebhook'):])
    if m_key:
        old_key = m_key.group(1)
        # Only add if not already there
        if 'sender_lid' not in code[m_key.start():m_key.end()+200]:
            new_key = old_key + "\n            ...(senderLid ? { sender_lid: senderLid } : {}),"
            abs_start = code.find('async function forwardToWebhook')
            rel_pos = code.find(old_key, abs_start)
            code = code[:rel_pos] + new_key + code[rel_pos+len(old_key):]
            changes += 1
            print('PATCH 2c OK: sender_lid added to payload')

    # Step 2d: Update log line to show resolved phone
    old_log = "console.log('[Webhook][' + phoneId + '] ' + resp.status);"
    new_log = "console.log('[Webhook][' + phoneId + '] ' + resp.status + ' sender=' + senderPhone + (senderLid ? ' (LID:' + senderLid + ')' : ''));"
    if old_log in code:
        code = code.replace(old_log, new_log, 1)
        changes += 1
        print('PATCH 2d OK: webhook log improved')
else:
    print('PATCH 2 SKIP: senderLid already in code')

if changes > 0:
    with open(path, 'w') as f:
        f.write(code)
    print(f'\n=== {changes} patches applied to {path} ===')
else:
    print('\nNo changes needed')
