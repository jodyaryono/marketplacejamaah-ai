#!/bin/bash
cd /var/www/integrasi-wa.jodyaryono.id

echo "=== Update webhook in DB ==="
node -e "
const pg = require('pg');
const pool = new pg.Pool({host:'localhost',port:5432,database:'integrasi_wa',user:'integrasi_wa',password:'integrasi2026'});
pool.query(
  'UPDATE wa_sessions SET webhook_url=\$1, webhook_enabled=true WHERE phone_id=\$2',
  ['https://marketplacejamaah-ai.jodyaryono.id/api/webhook/whacenter', '6281317647379']
).then(r => {
  console.log('Updated:', r.rowCount, 'rows');
  return pool.query('SELECT phone_id, webhook_url, webhook_enabled FROM wa_sessions');
}).then(r => {
  console.log(JSON.stringify(r.rows, null, 2));
  pool.end();
}).catch(e => { console.error(e.message); pool.end(); });
"

echo ""
echo "=== Restart to reload sessions ==="
supervisorctl restart integrasi-wa
sleep 10
tail -5 gateway.log
