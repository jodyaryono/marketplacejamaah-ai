#!/usr/bin/env python3
import sys

with open('/var/www/integrasi-wa.jodyaryono.id/index.js', 'r') as f:
    content = f.read()

with open('/var/www/integrasi-wa.jodyaryono.id/join_route.txt', 'r') as f:
    new_route = f.read()

marker = '// \u2500\u2500\u2500 START \u2500\u2500\u2500'
if marker in content:
    content = content.replace(marker, new_route + marker, 1)
    with open('/var/www/integrasi-wa.jodyaryono.id/index.js', 'w') as f:
        f.write(content)
    print('DONE')
else:
    # Try a fallback marker
    marker2 = 'async function main() {'
    if marker2 in content:
        content = content.replace(marker2, new_route + '\n' + marker2, 1)
        with open('/var/www/integrasi-wa.jodyaryono.id/index.js', 'w') as f:
            f.write(content)
        print('DONE_FALLBACK')
    else:
        print('MARKER NOT FOUND')
        sys.exit(1)
