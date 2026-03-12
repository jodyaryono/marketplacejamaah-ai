# WhatsApp Gateway

This folder contains the WhatsApp gateway source code (`index.js`) that runs on the server at:
`/var/www/integrasi-wa.jodyaryono.id/`

## ⚠️ Rules — NEVER break these

1. **Never edit `index.js` directly on the server.** Always edit here locally.
2. **All changes must go through `deploy-gateway.sh`**, which validates syntax and required event listeners before deploying.
3. After editing locally, deploy with:

```bash
# 1. Copy to server
scp -P 23232 gateway/index.js root@103.185.52.146:/tmp/index.js.new

# 2. Run the deploy script on server (validates + restarts)
ssh -p 23232 root@103.185.52.146 '/var/www/integrasi-wa.jodyaryono.id/deploy-gateway.sh /tmp/index.js.new'
```

4. The deploy script checks for these **required event listeners** — all must be present:
   - `client.on('message'`
   - `client.on('group_update'`
   - `client.on('group_join'`
   - `client.on('group_leave'`
   - `client.on('group_membership_request'`
   - `forwardToWebhook`

## Why this matters

In March 2026, a patch script edited `index.js` directly on the server and accidentally deleted the `group_join`, `group_leave`, and `group_membership_request` handlers. This broke onboarding for ~23 hours before the `gateway:verify` health check detected it.

Keeping `index.js` in git means every change is reviewed, tracked, and reversible.
