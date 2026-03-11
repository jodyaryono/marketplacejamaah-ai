#!/usr/bin/env python3
"""
Fix LID JID in approve/reject endpoints - accept requester_jid override.
Uses targeted line-by-line replacement instead of block matching.
"""
import sys

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    lines = f.readlines()

original = list(lines)
changes = 0

# Find approve-membership and reject-membership sections and patch them
in_approve = False
in_reject = False
approve_patched = False
reject_patched = False

for i, line in enumerate(lines):
    stripped = line.rstrip()

    # Detect which endpoint we're in
    if "app.post('/api/approve-membership'" in stripped:
        in_approve = True
        in_reject = False
    elif "app.post('/api/reject-membership'" in stripped:
        in_reject = True
        in_approve = False
    elif stripped == '});' and (in_approve or in_reject):
        in_approve = False
        in_reject = False

    if (in_approve or in_reject):
        # Patch 1: Destructure to include requester_jid
        if "const { group_id, requester } = req.body;" in stripped and 'reqJidOverride' not in stripped:
            indent = len(line) - len(line.lstrip())
            lines[i] = ' ' * indent + \
                "const { group_id, requester, requester_jid: reqJidOverride } = req.body;\n"
            changes += 1
            print(
                f'[{"approve" if in_approve else "reject"}] ✓ Added reqJidOverride destructure at line {i+1}')

        # Patch 2: Use reqJidOverride in JID construction
        if 'const requesterJid = numberToJid(String(requester)' in stripped and 'reqJidOverride' not in stripped:
            indent = len(line) - len(line.lstrip())
            lines[i] = ' ' * indent + \
                "const requesterJid = reqJidOverride || numberToJid(String(requester).replace(/\\D/g, ''));\n"
            changes += 1
            print(
                f'[{"approve" if in_approve else "reject"}] ✓ Use reqJidOverride in JID at line {i+1}')

        # Patch 3 (approve only): Fix cache clear to use cachePhone
        if in_approve and 'notified.delete' in stripped and 'String(requester)' in stripped and 'cachePhone' not in stripped:
            indent = len(line) - len(line.lstrip())
            lines[i] = (
                ' ' * indent +
                "const cachePhone = reqJidOverride ? reqJidOverride.replace(/@.*$/, '') : String(requester).replace(/\\D/g,'');\n"
                + ' ' * indent +
                "if (notified) notified.delete(`${cachePhone}@${group_id}`);\n"
            )
            changes += 1
            print(
                f'[approve] ✓ Fixed cache clear with cachePhone at line {i+1}')

        # Patch 4: Fix console.log to show JID if used
        if "console.log('[Approval] Approved'" in stripped and 'reqJidOverride' not in stripped:
            indent = len(line) - len(line.lstrip())
            lines[i] = ' ' * indent + \
                "console.log('[Approval] Approved ' + (reqJidOverride || requester) + ' for group ' + group_id);\n"
            changes += 1
            print(f'[approve] ✓ Fixed console.log at line {i+1}')

        if "console.log('[Approval] Rejected'" in stripped and 'reqJidOverride' not in stripped:
            indent = len(line) - len(line.lstrip())
            lines[i] = ' ' * indent + \
                "console.log('[Approval] Rejected ' + (reqJidOverride || requester) + ' for group ' + group_id);\n"
            changes += 1
            print(f'[reject] ✓ Fixed console.log at line {i+1}')

if changes == 0:
    print('No changes made - already patched?')
    sys.exit(0)

with open(path, 'w') as f:
    f.writelines(lines)

print(f'DONE ({changes} changes)')
