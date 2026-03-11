#!/bin/bash
cd /var/www/integrasi-wa.jodyaryono.id

# Fix 1: Remove --single-process from puppeteerArgs (causes instability)
# Fix 2: Add auto-retry on initialize error
sed -i "s/'--no-first-run', '--no-zygote', '--single-process'/'--no-first-run', '--no-zygote'/g" index.js

# Fix 3: Add retry logic to initialize catch block (in startSession)
# Replace the simple catch block with one that retries
sed -i '/\[WA\]\[.*\] Initialize error:.*e\.message/,/sess\.client = null;/{
  /sess\.client = null;/a\
        // Auto-retry on init failure\
        sess._failCount = (sess._failCount || 0) + 1;\
        const delay = sess._failCount >= 3 ? 5 * 60 * 1000 : 15000;\
        console.log("[WA][" + id + "] Init retry in " + (delay / 1000) + "s (attempt " + sess._failCount + ")");\
        sess._reconnectTimer = setTimeout(() => { sess._reconnectTimer = null; startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled); }, delay);
}' index.js

echo "=== Verify fix ==="
grep -n 'single-process' index.js && echo "FAIL: still has single-process" || echo "OK: single-process removed"
grep -n 'Init retry' index.js | head -5

echo "=== Restart ==="
supervisorctl stop integrasi-wa
sleep 2
supervisorctl start integrasi-wa
sleep 15
tail -15 gateway.log
