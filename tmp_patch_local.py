#!/usr/bin/env python3
"""Apply 4 patches to integrasi_index.js locally then we SCP it up."""
import sys

path = 'c:/laragon/www/marketplacejamaah-ai/integrasi_index.js'
with open(path, 'r', encoding='utf-8') as f:
    src = f.read()

original = src
errors = []

# ─────────────────────────────────────────────────────────────────────────────
# PATCH 1 — Make "X grup" column a clickable button
# OLD file line 579:
#   '      +"<td>"+s.groups+" grup</td>"' +
# ─────────────────────────────────────────────────────────────────────────────
p1_old = '      +"<td>"+s.groups+" grup</td>"'
p1_new = ('      +"<td><button data-pid=\\\'"+esc(s.phone_id)+"\\\'  onclick=\\\'showGroups(this.dataset.pid)\\\'  style=\\\'background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;border-radius:7px;padding:2px 12px;font-size:.8rem;font-weight:700;cursor:pointer;\\\'>"+s.groups+" grup &#128065;</button></td>"')

# We search for the old line as it literally appears in the file
OLD1 = "        '" + p1_old + "' +"
NEW1 = "        '" + p1_new + "' +"

if OLD1 in src:
    src = src.replace(OLD1, NEW1, 1)
    print('PATCH1 OK')
elif 'showGroups(this.dataset.pid)' in src:
    print('PATCH1 SKIP (already applied)')
else:
    errors.append('PATCH1 NOT FOUND')
    # Debug
    i = src.find('s.groups')
    print(f'  Debug: s.groups found at {i} ctx={repr(src[max(0,i-20):i+40])}')

# ─────────────────────────────────────────────────────────────────────────────
# PATCH 2 — Add showGroups() + leaveGroup() to the dashboard page
# Strategy: add a const jsGroups = `<script>...</script>` (backtick template
# literal) right after the `js` variable, then include it in res.send().
# Using a template literal avoids ALL nested quoting issues.
# ─────────────────────────────────────────────────────────────────────────────

JS_GROUPS = r"""
    const jsGroups = `<script>
async function showGroups(pid) {
  var ov = document.createElement('div');
  ov.id = 'sg-overlay';
  ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center;';
  ov.innerHTML = '<div style="background:#fff;border-radius:16px;padding:24px 28px;max-width:660px;width:95%;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.2);">'
    + '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">'
    + '<b style="font-size:1rem;">Grup: <span id="sg-pid" style="color:#059669;font-family:monospace;font-size:.9rem;"></span></b>'
    + '<button id="sg-close" style="background:#f3f4f6;border:none;border-radius:8px;padding:4px 14px;cursor:pointer;font-size:1.1rem;">&#10005;</button>'
    + '</div>'
    + '<div id="sg-body" style="overflow-y:auto;flex:1;min-height:80px;"><div style="text-align:center;padding:24px;color:#6b7280;">Memuat...</div></div>'
    + '</div>';
  document.body.appendChild(ov);
  document.getElementById('sg-close').onclick = function() { ov.remove(); };
  ov.onclick = function(e) { if (e.target === ov) ov.remove(); };
  document.getElementById('sg-pid').textContent = pid;
  try {
    var r = await fetch('/api/groups?phone_id=' + encodeURIComponent(pid));
    var d = await r.json();
    var gs = d.groups || [];
    if (!gs.length) {
      document.getElementById('sg-body').innerHTML = '<div style="text-align:center;padding:24px;color:#6b7280;">Tidak ada grup.</div>';
      return;
    }
    var rows = gs.map(function(g) {
      var badge = (g.role === 'admin' || g.role === 'superadmin')
        ? '<span style="background:#dcfce7;color:#166534;border-radius:5px;font-size:.7rem;font-weight:700;padding:2px 8px;">&#128081; Admin</span>'
        : '<span style="background:#f3f4f6;color:#374151;border-radius:5px;font-size:.7rem;font-weight:600;padding:2px 8px;">&#128100; Member</span>';
      return '<tr>'
        + '<td style="padding:9px 12px;font-weight:600;">' + esc(g.name) + '</td>'
        + '<td style="padding:9px 12px;">' + badge + '</td>'
        + '<td style="padding:9px 12px;color:#6b7280;font-size:.78rem;">' + g.participants + ' anggota</td>'
        + '<td style="padding:9px 12px;"><button class="sg-lv" data-jid="' + esc(g.jid) + '" data-name="' + esc(g.name) + '" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:7px;font-size:.75rem;padding:3px 12px;cursor:pointer;font-weight:600;">&#11013; Keluar</button></td>'
        + '</tr>';
    }).join('');
    document.getElementById('sg-body').innerHTML = '<table style="width:100%;border-collapse:collapse;"><thead><tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb;">'
      + '<th style="padding:8px 12px;text-align:left;font-size:.72rem;color:#6b7280;font-weight:600;">Nama Grup</th>'
      + '<th style="padding:8px 12px;text-align:left;font-size:.72rem;color:#6b7280;font-weight:600;">Peran</th>'
      + '<th style="padding:8px 12px;text-align:left;font-size:.72rem;color:#6b7280;font-weight:600;">Anggota</th>'
      + '<th style="padding:8px 12px;text-align:left;font-size:.72rem;color:#6b7280;font-weight:600;">Aksi</th>'
      + '</tr></thead><tbody>' + rows + '</tbody></table>';
    document.getElementById('sg-body').querySelectorAll('.sg-lv').forEach(function(b) {
      b.onclick = function() { leaveGroup(pid, b.dataset.jid, b.dataset.name); };
    });
  } catch(e) {
    document.getElementById('sg-body').innerHTML = '<div style="text-align:center;padding:24px;color:#dc2626;">Gagal: ' + e.message + '</div>';
  }
}
async function leaveGroup(pid, groupId, groupName) {
  if (!confirm('Yakin keluar dari grup: ' + groupName + '?')) return;
  try {
    var r = await fetch('/api/leave-group', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({phone_id: pid, group_id: groupId})
    });
    var d = await r.json();
    if (d.success) {
      toast('Berhasil keluar dari ' + groupName);
      var o = document.getElementById('sg-overlay');
      if (o) o.remove();
      refresh();
    } else {
      toast(d.error || 'Gagal keluar dari grup', 'err');
    }
  } catch(e) {
    toast('Error: ' + e.message, 'err');
  }
}
</script>`;
"""

# Anchor: the end of `js` variable, then blank line, then `const modal =`
P2_ANCHOR  = "        '</script>';\n\n    const modal ="
P2_REPLACE = "        '</script>';\n" + JS_GROUPS + "\n    const modal ="

# Also change res.send to include jsGroups
P2B_ANCHOR  = "stats + table + modal + histModal + js, 'device'"
P2B_REPLACE = "stats + table + modal + histModal + js + jsGroups, 'device'"

if 'async function showGroups' not in src and 'jsGroups' not in src:
    if P2_ANCHOR in src:
        src = src.replace(P2_ANCHOR, P2_REPLACE, 1)
        src = src.replace(P2B_ANCHOR, P2B_REPLACE, 1)
        print('PATCH2 OK')
    else:
        errors.append('PATCH2 anchor not found')
        # Debug: show what's around </script>
        i = src.find("'</script>';")
        print(f'  Debug PATCH2: </script> at {i}, nearby: {repr(src[i-20:i+60])}')
elif 'jsGroups' in src:
    print('PATCH2 SKIP (already applied)')
else:
    print('PATCH2 SKIP (showGroups exists already)')

# ─────────────────────────────────────────────────────────────────────────────
# PATCH 3 — /api/groups returns role field
# ─────────────────────────────────────────────────────────────────────────────
OLD3 = (
    "app.get('/api/groups', apiAuth, (req, res) => {\n"
    "    const pid = sanitizeId(req.query.phone_id || '') || getFirstOpenSession();\n"
    "    const s = sessions.get(pid);\n"
    "    if (!s) return res.status(404).json({ error: 'Session not found' });\n"
    "    const groups = [];\n"
    "    for (const [jid, meta] of s.groupCache) groups.push({ jid, name: meta.subject, participants: meta.participants?.length || 0 });\n"
    "    res.json({ phone_id: pid, groups });\n"
    "});"
)
NEW3 = (
    "app.get('/api/groups', apiAuth, (req, res) => {\n"
    "    const pid = sanitizeId(req.query.phone_id || '') || getFirstOpenSession();\n"
    "    const s = sessions.get(pid);\n"
    "    if (!s) return res.status(404).json({ error: 'Session not found' });\n"
    "    const groups = [];\n"
    "    const botJid = pid + '@c.us';\n"
    "    for (const [jid, meta] of s.groupCache) {\n"
    "        const participants = meta.participants || [];\n"
    "        const me = participants.find(p => (p.id && p.id._serialized || p.id || '') === botJid);\n"
    "        const role = me && me.isSuperAdmin ? 'superadmin' : me && me.isAdmin ? 'admin' : 'member';\n"
    "        groups.push({ jid, name: meta.subject, participants: participants.length, role });\n"
    "    }\n"
    "    res.json({ phone_id: pid, groups });\n"
    "});"
)
if OLD3 in src:
    src = src.replace(OLD3, NEW3, 1)
    print('PATCH3 OK')
elif 'botJid' in src:
    print('PATCH3 SKIP (already applied)')
else:
    errors.append('PATCH3 NOT FOUND')

# ─────────────────────────────────────────────────────────────────────────────
# PATCH 4 — Add /api/leave-group endpoint before // ─── START
# ─────────────────────────────────────────────────────────────────────────────
# Find the exact line with '─── START'
start_line = None
for line in src.splitlines():
    if '\u2500\u2500\u2500 START \u2500' in line:
        start_line = line
        break

if start_line is None:
    errors.append('PATCH4: could not find START anchor line')
else:
    LEAVE_ENDPOINT = (
        "app.post('/api/leave-group', apiAuth, async (req, res) => {\n"
        "    try {\n"
        "        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();\n"
        "        const s = sessions.get(pid);\n"
        "        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected' });\n"
        "        const groupId = (req.body.group_id || '').trim();\n"
        "        if (!groupId) return res.status(400).json({ error: 'group_id required' });\n"
        "        const chat = await s.client.getChatById(groupId);\n"
        "        await chat.leave();\n"
        "        s.groupCache.delete(groupId);\n"
        "        res.json({ success: true, phone_id: pid, group_id: groupId });\n"
        "    } catch (e) { res.status(500).json({ success: false, error: e.message }); }\n"
        "});\n\n"
        + start_line
    )
    if "app.post('/api/leave-group'" in src:
        print('PATCH4 SKIP (already applied)')
    elif start_line in src:
        src = src.replace(start_line, LEAVE_ENDPOINT, 1)
        print('PATCH4 OK')
    else:
        errors.append('PATCH4: start_line not in src')

# ─────────────────────────────────────────────────────────────────────────────
# Save
# ─────────────────────────────────────────────────────────────────────────────
if src != original:
    with open(path, 'w', encoding='utf-8') as f:
        f.write(src)
    print('\nFile saved.')
else:
    print('\nNo changes written.')

if errors:
    print('\nERRORS:', errors)
    sys.exit(1)
else:
    print('ALL DONE.')
