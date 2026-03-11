src = open('c:/laragon/www/marketplacejamaah-ai/integrasi_index.js', encoding='utf-8').read()
checks = [
    ('grup button', 'grup 👁</button>' in src),
    ('showGroups fn', 'async function showGroups(pid)' in src),
    ('api/groups role', 'const botJid = pid' in src),
    ('api/leave-group', "app.post('/api/leave-group'" in src),
    ('apiAuth session', 'req.session && req.session.loggedIn' in src),
]
for name, found in checks:
    print(name, 'OK' if found else 'MISSING')
print('lines:', src.count('\n'))
