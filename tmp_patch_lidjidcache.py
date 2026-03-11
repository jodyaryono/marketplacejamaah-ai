path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    c = f.read()

original = c

# PATCH 1: Add lidJidCache after numberToJid function
old1 = "function resolveGroupJid(nameOrJid, groupCache) {"
new1 = """// LID JID cache: populated from incoming messages so replies use exact known JID
const lidJidCache = new Map();

function resolveGroupJid(nameOrJid, groupCache) {"""
if old1 in c:
    c = c.replace(old1, new1, 1)
    print('PATCH 1 OK: lidJidCache added')
else:
    print('PATCH 1 FAILED: resolveGroupJid not found')

# PATCH 2: Update numberToJid to check cache first
old2 = "    if (num.length >= 14) return num + '@lid';\n    return num + '@c.us';"
new2 = "    if (lidJidCache.has(num)) return lidJidCache.get(num);\n    if (num.length >= 14) return num + '@lid';\n    return num + '@c.us';"
if old2 in c:
    c = c.replace(old2, new2, 1)
    print('PATCH 2 OK: numberToJid cache lookup added')
else:
    print('PATCH 2 FAILED: @lid line not found')

# PATCH 3: Populate cache in forwardToWebhook from incoming @lid messages
old3 = "    const fromNum = isGroup ? cleanJid(msg.author || '') : cleanJid(jid);\n    // Log to DB"
new3 = """    const fromNum = isGroup ? cleanJid(msg.author || '') : cleanJid(jid);
    // Cache @lid JIDs so replies use the correct JID format
    const authorJid = isGroup ? (msg.author || '') : jid;
    if (authorJid.endsWith('@lid') && fromNum) {
        lidJidCache.set(fromNum, authorJid);
    }
    // Log to DB"""
if old3 in c:
    c = c.replace(old3, new3, 1)
    print('PATCH 3 OK: LID JID cache population added')
else:
    print('PATCH 3 FAILED: fromNum line not found')

if c != original:
    with open(path, 'w') as f:
        f.write(c)
    print('FILE WRITTEN')
else:
    print('NO CHANGES MADE')
