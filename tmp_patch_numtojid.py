import re

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    content = f.read()

old = "    return num + '@c.us';\n}"
new = "    if (num.length >= 14) return num + '@lid';\n    return num + '@c.us';\n}"

if old in content:
    content = content.replace(old, new, 1)
    with open(path, 'w') as f:
        f.write(content)
    print('PATCHED')
else:
    print('NOT FOUND')
    # try to find what is there
    idx = content.find("return num + '")
    if idx >= 0:
        print(repr(content[idx-5:idx+40]))
