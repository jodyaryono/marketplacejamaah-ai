path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r') as f:
    c = f.read()

# Add resync function after lidJidCache declaration
old_fn = "// LID JID cache: populated from incoming messages so replies use exact known JID\nconst lidJidCache = new Map();\n"
new_fn = """// LID JID cache: populated from incoming messages so replies use exact known JID
const lidJidCache = new Map();

// Resync lidJidCache from DB on reconnect (restores @lid routing after restart)
async function resyncLidJidCache() {
    try {
        const { rows } = await db.query(
            "SELECT DISTINCT from_number, wa_msg_id FROM messages_log WHERE direction='in' AND length(from_number) >= 14 AND wa_msg_id LIKE '%@lid%' ORDER BY created_at DESC LIMIT 500"
        );
        let count = 0;
        for (const row of rows) {
            // wa_msg_id format: false_{fromJid}_{msgId} or true_{fromJid}_{msgId}
            const m = row.wa_msg_id && row.wa_msg_id.match(/^(?:false|true)_(\\d+@lid)_/);
            if (m && row.from_number) {
                lidJidCache.set(row.from_number, m[1]);
                count++;
            }
        }
        if (count > 0) console.log('[LID] Resynced ' + count + ' LID JIDs from DB');
    } catch (e) { console.warn('[LID] resync failed:', e.message); }
}

"""
if old_fn in c:
    c = c.replace(old_fn, new_fn, 1)
    print('PATCH 1 OK: resyncLidJidCache function added')
else:
    print('PATCH 1 FAILED')

# Call resync in the ready event, after refreshGroupCacheForSession
old_ready = "        refreshGroupCacheForSession(id);\n    });"
new_ready = "        refreshGroupCacheForSession(id);\n        resyncLidJidCache();\n    });"
if old_ready in c:
    c = c.replace(old_ready, new_ready, 1)
    print('PATCH 2 OK: resyncLidJidCache called on ready')
else:
    print('PATCH 2 FAILED')

with open(path, 'w') as f:
    f.write(c)
print('DONE')
