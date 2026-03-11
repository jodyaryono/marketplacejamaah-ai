#!/bin/bash
echo "=== Memory ==="
free -m

echo "=== whatsapp-web.js version ==="
node -e "const p=require('/var/www/integrasi-wa.jodyaryono.id/node_modules/whatsapp-web.js/package.json');console.log(p.version)"

echo "=== puppeteer version ==="
node -e "try{const p=require('/var/www/integrasi-wa.jodyaryono.id/node_modules/puppeteer-core/package.json');console.log(p.version)}catch(e){try{const p=require('/var/www/integrasi-wa.jodyaryono.id/node_modules/puppeteer/package.json');console.log(p.version)}catch(e2){console.log('not found')}}"

echo "=== Chrome test ==="
timeout 5 google-chrome-stable --headless --no-sandbox --disable-gpu --dump-dom about:blank 2>&1 | head -5

echo "=== Session auth files ==="
ls -la /var/www/integrasi-wa.jodyaryono.id/auth_info/session-6281317647379/ 2>/dev/null | head -10
