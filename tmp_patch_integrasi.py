#!/usr/bin/env python3
import re

path = '/var/www/integrasi-wa.jodyaryono.id/index.js'
with open(path, 'r', encoding='utf-8') as f:
    src = f.read()

# ── 1. Make "X grup" column clickable ────────────────────────────────────────
old1 = '      +"<td>"+s.groups+" grup</td>"'
new1 = '      +"<td><button class=\'btn btn-ghost btn-sm\' style=\'font-weight:600;color:#059669;border-color:#a7f3d0;background:#ecfdf5;padding:2px 10px;\' data-pid=\'"+esc(s.phone_id)+"\' onclick=\'showGroups(this.dataset.pid)\'>"+s.groups+" grup 👁</button></td>"'
assert old1 in src, 'PATCH1 not found'
src = src.replace(old1, new1, 1)
print('PATCH1 OK')

# ── 2. Add showGroups() + leaveGroup() JS function after the disc() function ──
old2 = '''async function disc(id){
  if(!confirm("Disconnect sesi \\""+id+"\\"? Perangkat akan terputus dari WhatsApp."))return;
  try{
    const r=await fetch("/web/session/"+encodeURIComponent(id)+"/disconnect",{method:"POST",headers:{"Content-Type":"application/json"}});
    const d=await r.json();
    if(d.success){toast("Sesi "+id+" disconnected");refresh();}else toast(d.error||"Gagal disconnect","err");'''

# find disc function end
disc_end_marker = 'async function disc(id){'
pos = src.find(disc_end_marker)
assert pos != -1, 'disc() not found'
# find closing of that function - find next top-level 'async function' after disc
next_func = src.find('\nasync function ', pos + 10)
if next_func == -1:
    next_func = src.find('\nfunction ', pos + 10)

# insert before next async function
insert_at = next_func
show_groups_fn = r"""
async function showGroups(pid){
  const overlay=document.createElement("div");
  overlay.id="sg-overlay";
  overlay.style.cssText="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:flex;align-items:center;justify-content:center;";
  overlay.innerHTML='<div style="background:#fff;border-radius:16px;padding:24px;max-width:680px;width:95%;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.18);">'
    +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">'
    +'<span style="font-size:1.1rem;font-weight:700;color:#111827;">👥 Daftar Grup — <span id="sg-pid" style="color:#059669"></span></span>'
    +'<button onclick="document.getElementById(\'sg-overlay\').remove()" style="background:#f3f4f6;border:none;border-radius:8px;padding:4px 12px;cursor:pointer;font-size:1.1rem;">✕</button>'
    +'</div>'
    +'<div id="sg-body" style="overflow-y:auto;flex:1;"><div style="text-align:center;padding:24px;color:#6b7280;">Memuat...</div></div>'
    +'</div>';
  document.body.appendChild(overlay);
  overlay.addEventListener('click', e => { if(e.target===overlay) overlay.remove(); });
  document.getElementById('sg-pid').textContent = pid;
  try {
    const r = await fetch('/api/groups?phone_id='+encodeURIComponent(pid), {headers:{'Authorization':'Bearer '+window._apiToken}});
    const d = await r.json();
    const groups = d.groups || [];
    if(!groups.length){ document.getElementById('sg-body').innerHTML='<div style="text-align:center;padding:24px;color:#6b7280;">Tidak ada grup.</div>'; return; }
    const roleBadge = role => {
      if(role==='superadmin'||role==='admin') return '<span style="background:#dcfce7;color:#15803d;border-radius:5px;font-size:.7rem;font-weight:700;padding:2px 8px;">👑 Admin</span>';
      return '<span style="background:#f3f4f6;color:#374151;border-radius:5px;font-size:.7rem;font-weight:600;padding:2px 8px;">👤 Member</span>';
    };
    const rows = groups.map(g =>
      '<tr>'
      +'<td style="padding:9px 10px;font-weight:600;color:#111827;vertical-align:middle;">'+esc(g.name)+'</td>'
      +'<td style="padding:9px 10px;vertical-align:middle;">'+roleBadge(g.role)+'</td>'
      +'<td style="padding:9px 10px;color:#6b7280;font-size:.75rem;vertical-align:middle;">'+g.participants+' anggota</td>'
      +'<td style="padding:9px 10px;vertical-align:middle;">'
      +'<button onclick="leaveGroup(\''+esc(pid)+'\',\''+esc(g.jid)+'\',\''+esc(g.name)+'\')" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:7px;font-size:.75rem;font-weight:600;padding:3px 10px;cursor:pointer;">⬅ Keluar</button>'
      +'</td>'
      +'</tr>'
    ).join('');
    document.getElementById('sg-body').innerHTML =
      '<table style="width:100%;border-collapse:collapse;">'
      +'<thead><tr style="background:#f9fafb;"><th style="padding:8px 10px;text-align:left;font-size:.72rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Nama Grup</th>'
      +'<th style="padding:8px 10px;text-align:left;font-size:.72rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Peran</th>'
      +'<th style="padding:8px 10px;text-align:left;font-size:.72rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Anggota</th>'
      +'<th style="padding:8px 10px;text-align:left;font-size:.72rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Aksi</th></tr></thead>'
      +'<tbody>'+rows+'</tbody></table>';
  } catch(e){ document.getElementById('sg-body').innerHTML='<div style="text-align:center;padding:24px;color:#dc2626;">Gagal memuat: '+e.message+'</div>'; }
}
async function leaveGroup(pid, groupId, groupName){
  if(!confirm('Yakin keluar dari grup "'+groupName+'"?')) return;
  try {
    const r = await fetch('/api/leave-group', {method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+window._apiToken},body:JSON.stringify({phone_id:pid,group_id:groupId})});
    const d = await r.json();
    if(d.success){ toast('✅ Berhasil keluar dari '+groupName); document.getElementById('sg-overlay')?.remove(); refresh(); }
    else toast(d.error||'Gagal keluar dari grup','err');
  } catch(e){ toast('Error: '+e.message,'err'); }
}
"""
src = src[:insert_at] + show_groups_fn + src[insert_at:]
print('PATCH2 OK')

# ── 3. Enhance /api/groups to return role ────────────────────────────────────
old3 = """app.get('/api/groups', apiAuth, (req, res) => {
    const pid = sanitizeId(req.query.phone_id || '') || getFirstOpenSession();
    const s = sessions.get(pid);
    if (!s) return res.status(404).json({ error: 'Session not found' });
    const groups = [];
    for (const [jid, meta] of s.groupCache) groups.push({ jid, name: meta.subject, participants: meta.participants?.length || 0 });
    res.json({ phone_id: pid, groups });
});"""
new3 = """app.get('/api/groups', apiAuth, (req, res) => {
    const pid = sanitizeId(req.query.phone_id || '') || getFirstOpenSession();
    const s = sessions.get(pid);
    if (!s) return res.status(404).json({ error: 'Session not found' });
    const groups = [];
    const botJid = pid + '@c.us';
    for (const [jid, meta] of s.groupCache) {
        const participants = meta.participants || [];
        const me = participants.find(p => (p.id?._serialized || p.id || '') === botJid);
        const role = me?.isSuperAdmin ? 'superadmin' : me?.isAdmin ? 'admin' : 'member';
        groups.push({ jid, name: meta.subject, participants: participants.length, role });
    }
    res.json({ phone_id: pid, groups });
});"""
assert old3 in src, 'PATCH3 not found'
src = src.replace(old3, new3, 1)
print('PATCH3 OK')

# ── 4. Add /api/leave-group endpoint before "// ─── START" ───────────────────
old4 = """// ─── START ────────────────────────────────────────────────────────────────────"""
new4 = """app.post('/api/leave-group', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected' });
        const groupId = (req.body.group_id || '').trim();
        if (!groupId) return res.status(400).json({ error: 'group_id required' });
        const chat = await s.client.getChatById(groupId);
        await chat.leave();
        s.groupCache.delete(groupId);
        res.json({ success: true, phone_id: pid, group_id: groupId });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});

// ─── START ────────────────────────────────────────────────────────────────────"""
assert old4 in src, 'PATCH4 not found'
src = src.replace(old4, new4, 1)
print('PATCH4 OK')

# ── 5. Expose API token to window for JS use ─────────────────────────────────
# Find where the HTML layout is built - look for a script tag that sets up the app
# We need window._apiToken to be set. Find the layout function or a meta tag approach.
# Actually, the frontend calls the API with Bearer token. Let's find where the token is passed to the JS.
# Check if there's already a _apiToken in the JS
if 'window._apiToken' not in src:
    # Find the layout function output - look for </head> in the HTML string
    old5 = "'</head>'"
    if old5 in src:
        new5 = "'<script>window._apiToken=document.cookie.split(\";\").map(c=>c.trim()).filter(c=>c.startsWith(\"api_token=\"))[0]?.split(\"=\")[1]||\"\";</script></head>'"
        src = src.replace(old5, new5, 1)
        print('PATCH5 OK (cookie-based token)')
    else:
        # Alternative: look for a meta tag or any place to inject it
        # Check if the API calls already have the auth baked in
        print('PATCH5 SKIPPED - no </head> found, checking existing auth...')
        # Look for how existing API calls pass tokens
        if 'Bearer' in src and 'window._apiToken' not in src:
            # Check if auth is done via cookie only
            if "credentials:'include'" in src or "credentials: 'include'" in src:
                # Cookie-based, no token needed in JS
                print('Auth via cookies - no window._apiToken needed')
                # Remove the auth header from our showGroups/leaveGroup
                src = src.replace(",'Authorization':'Bearer '+window._apiToken", '')
                src = src.replace(",'Authorization':'Bearer '+window._apiToken", '')
                print('PATCH5 OK (removed Bearer header, cookie auth)')
else:
    print('PATCH5 SKIPPED (already present)')

with open(path, 'w', encoding='utf-8') as f:
    f.write(src)
print('ALL PATCHES DONE')
