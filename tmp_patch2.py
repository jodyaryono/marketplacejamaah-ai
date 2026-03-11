#!/usr/bin/env python3
path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    src = f.read()

# 1. Fix apiAuth to allow web sessions
OLD = 'function apiAuth(req, res, next) {\n    if (!AUTH_TOKEN) return next();\n    const header = req.headers.authorization || \'\';\n    const token = header.startsWith(\'Bearer \') ? header.slice(7) : req.body?.token || req.query?.token;\n    if (token === AUTH_TOKEN) return next();\n    return res.status(401).json({ error: \'Unauthorized\' });\n}'
NEW = 'function apiAuth(req, res, next) {\n    if (!AUTH_TOKEN) return next();\n    if (req.session && req.session.loggedIn) return next(); // allow web session\n    const header = req.headers.authorization || \'\';\n    const token = header.startsWith(\'Bearer \') ? header.slice(7) : req.body?.token || req.query?.token;\n    if (token === AUTH_TOKEN) return next();\n    return res.status(401).json({ error: \'Unauthorized\' });\n}'

if OLD in src:
    src = src.replace(OLD, NEW, 1)
    print('P1 OK - apiAuth allows web sessions')
else:
    print('P1 NOT FOUND - checking existing...')
    if 'req.session.loggedIn' in src or 'req.session && req.session.loggedIn' in src:
        print('P1 already patched')
    else:
        print('ERROR: could not find apiAuth block')

# 2. Remove Bearer auth headers from our new showGroups/leaveGroup functions
# The session cookie is sent automatically with same-origin fetch
src = src.replace(",headers:{'Authorization':'Bearer '+window._apiToken}", '')
src = src.replace(",headers:{'Content-Type':'application/json','Authorization':'Bearer '+window._apiToken}", ",headers:{'Content-Type':'application/json'}")
print('P2 OK - removed Bearer from showGroups/leaveGroup')

with open(path, 'w') as f:
    f.write(src)
print('DONE')
