path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    c = f.read()

# Fix 1: remove duplicate lidJidCache.has line in numberToJid
c = c.replace(
    "    if (lidJidCache.has(num)) return lidJidCache.get(num);\n    if (lidJidCache.has(num)) return lidJidCache.get(num);",
    "    if (lidJidCache.has(num)) return lidJidCache.get(num);"
)

# Fix 2: remove duplicate const declaration block (keep only one)
dupe = "// LID JID cache: populated from incoming messages so replies use exact known JID\nconst lidJidCache = new Map();\n\n// LID JID cache: populated from incoming messages so replies use exact known JID\nconst lidJidCache = new Map();"
single = "// LID JID cache: populated from incoming messages so replies use exact known JID\nconst lidJidCache = new Map();"
if dupe in c:
    c = c.replace(dupe, single)
    print('Fixed dupe const')

# Fix 3: move const declaration BEFORE numberToJid function (it's used inside it)
# Remove it from after the function
c = c.replace(
    "}\n// LID JID cache: populated from incoming messages so replies use exact known JID\nconst lidJidCache = new Map();\n\nfunction resolveGroupJid",
    "}\nfunction resolveGroupJid"
)
# Ensure it exists before numberToJid
if "// LID JID cache" not in c[:c.find("function numberToJid")]:
    c = c.replace(
        "function numberToJid(number) {",
        "// LID JID cache: populated from incoming messages so replies use exact known JID\nconst lidJidCache = new Map();\n\nfunction numberToJid(number) {"
    )
    print('Moved const before numberToJid')

with open(path, 'w') as f:
    f.write(c)
print('DONE')
