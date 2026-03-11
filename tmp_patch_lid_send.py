#!/usr/bin/env python3
import re

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    code = f.read()

# Find numberToJid function and patch it
m = re.search(r'function numberToJid\(number\) \{.*?\}', code, re.DOTALL)
if not m:
    print('PATTERN NOT FOUND')
    exit(1)

old_func = m.group(0)
print('Found function:')
print(old_func[:200])

# Check if already patched
if '@lid' in old_func:
    print('ALREADY PATCHED')
    exit(0)

# Replace the last return line
new_func = old_func.replace(
    "    return num + '@c.us';\n}",
    "    // WhatsApp LID numbers are 14+ digits - use @lid suffix, not @c.us\n    if (num.length >= 14) return num + '@lid';\n    return num + '@c.us';\n}"
)

if new_func == old_func:
    print('REPLACEMENT FAILED - return line not found')
    exit(1)

code = code.replace(old_func, new_func, 1)
with open(path, 'w') as f:
    f.write(code)
print('PATCHED: numberToJid now handles LID numbers')
