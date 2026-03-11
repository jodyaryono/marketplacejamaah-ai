path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    c = f.read()

old = "    else if (num.startsWith('8') && num.length >= 9 && num.length <= 13) num = '62' + num;\n    return num + '@c.us';"
new = "    else if (num.startsWith('8') && num.length >= 9 && num.length <= 13) num = '62' + num;\n    if (num.length >= 14) return num + '@lid';\n    return num + '@c.us';"

if old in c:
    with open(path, 'w') as f:
        f.write(c.replace(old, new, 1))
    print('PATCHED OK')
else:
    idx = c.find('function numberToJid')
    print('NOT_FOUND, current block:')
    print(repr(c[idx:idx+250]))
