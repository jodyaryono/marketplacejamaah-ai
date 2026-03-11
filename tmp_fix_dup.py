path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path) as f:
    c = f.read()
bad = "    if (num.length >= 14) return num + '@lid';\n    if (num.length >= 14) return num + '@lid';"
good = "    if (num.length >= 14) return num + '@lid';"
if bad in c:
    c = c.replace(bad, good, 1)
    with open(path,'w') as f:
        f.write(c)
    print('FIXED')
else:
    print('OK - no duplicate found')
