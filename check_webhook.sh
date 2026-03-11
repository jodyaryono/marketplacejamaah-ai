#!/bin/bash
echo "=== Webhook Config ==="
grep -E 'WEBHOOK' /var/www/integrasi-wa.jodyaryono.id/.env

echo ""
echo "=== Webhook in DB ==="
cd /var/www/integrasi-wa.jodyaryono.id
node -e "
const pg = require('pg');
const pool = new pg.Pool({host:'localhost',port:5432,database:'integrasi_wa',user:'integrasi_wa',password:'integrasi2026'});
pool.query('SELECT phone_id, webhook_url, webhook_enabled FROM wa_sessions').then(r => {
  console.log(JSON.stringify(r.rows, null, 2));
  pool.end();
}).catch(e => { console.error(e.message); pool.end(); });
"

echo ""
echo "=== Webhook forward function ==="
grep -n 'forwardToWebhook\|webhook\|WEBHOOK' /var/www/integrasi-wa.jodyaryono.id/index.js | head -20
