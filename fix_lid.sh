#!/bin/bash
cd /var/www/integrasi-wa.jodyaryono.id

# Fix: resolve LID to real phone number using contact.number
# Old: const fromNum = isGroup ? (msg.author || '').replace('@c.us', '') : jid.replace('@c.us', '');
# New: resolve using contact's phone number if available

cat > /tmp/fix_lid.js << 'PATCHEOF'
const fs = require('fs');
let code = fs.readFileSync('/var/www/integrasi-wa.jodyaryono.id/index.js', 'utf8');

// Fix fromNum extraction to handle @lid format
const oldFromNum = "const fromNum = isGroup ? (msg.author || '').replace('@c.us', '') : jid.replace('@c.us', '');";
const newFromNum = `let fromNum = isGroup ? (msg.author || '').replace('@c.us', '').replace('@lid', '') : jid.replace('@c.us', '').replace('@lid', '');`;

if (code.includes(oldFromNum)) {
    code = code.replace(oldFromNum, newFromNum);
    console.log('Fixed fromNum @lid strip');
} else {
    console.log('fromNum already patched or not found');
}

// Fix: also resolve contact.number for real phone in payload sender
const oldSender = "sender: fromNum, sender_name: pushName, from: jid.replace('@c.us', '').replace('@g.us', ''),";
const newSender = "sender: contact?.number || fromNum, sender_name: pushName, from: jid.replace('@c.us', '').replace('@g.us', '').replace('@lid', ''),";

if (code.includes(oldSender)) {
    code = code.replace(oldSender, newSender);
    console.log('Fixed sender to use contact.number');
} else {
    console.log('sender already patched or not found');
}

fs.writeFileSync('/var/www/integrasi-wa.jodyaryono.id/index.js', code);
console.log('Done');
PATCHEOF

node /tmp/fix_lid.js

echo ""
echo "=== Verify ==="
grep -n 'contact?.number\|@lid' index.js | head -10

echo ""
echo "=== Restart ==="
supervisorctl restart integrasi-wa
sleep 15
tail -5 gateway.log
