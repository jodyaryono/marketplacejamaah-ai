import 'dotenv/config';
import express from 'express';
import session from 'express-session';
process.on('unhandledRejection', (err) => { console.error('[UNHANDLED]', err?.message || err); });
import pkg from 'whatsapp-web.js';
const { Client, LocalAuth, MessageMedia } = pkg;
import QRCode from 'qrcode';
import fs from 'fs';
import path from 'path';
import https from 'https';
import { randomBytes } from 'crypto';
import pg from 'pg';
const { Pool } = pg;

// ─── CONFIG ──────────────────────────────────────────────────────────────────
const PORT = parseInt(process.env.PORT || '3001', 10);
const AUTH_TOKEN = process.env.AUTH_TOKEN || '';
const WEBHOOK_URL = process.env.WEBHOOK_URL || '';
const WEBHOOK_ENABLED = process.env.WEBHOOK_ENABLED === 'true';
const ADMIN_USER = process.env.ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASS || 'admin123';
const SESSION_SECRET = process.env.SESSION_SECRET || 'integrasi-wa-secret-2026';
const CHROME_PATH = process.env.CHROME_PATH || '';

// ─── DATABASE ─────────────────────────────────────────────────────────────────
const db = new Pool({
    host: process.env.DB_HOST || 'localhost',
    port: parseInt(process.env.DB_PORT || '5432'),
    database: process.env.DB_NAME || 'integrasi_wa',
    user: process.env.DB_USER || 'integrasi_wa',
    password: process.env.DB_PASS || 'integrasi2026',
    ssl: process.env.DB_SSL === 'true' ? { rejectUnauthorized: false } : false,
});

async function initDb() {
    await db.query(`
        CREATE TABLE IF NOT EXISTS wa_sessions (
            phone_id        VARCHAR(100) PRIMARY KEY,
            label           VARCHAR(200) NOT NULL DEFAULT '',
            api_token       VARCHAR(100) NOT NULL DEFAULT '',
            webhook_url     TEXT         NOT NULL DEFAULT '',
            webhook_enabled BOOLEAN      NOT NULL DEFAULT FALSE,
            created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        );
        ALTER TABLE wa_sessions ADD COLUMN IF NOT EXISTS api_token VARCHAR(100) NOT NULL DEFAULT '';
        ALTER TABLE wa_sessions ADD COLUMN IF NOT EXISTS webhook_url TEXT NOT NULL DEFAULT '';
        ALTER TABLE wa_sessions ADD COLUMN IF NOT EXISTS webhook_enabled BOOLEAN NOT NULL DEFAULT FALSE;
        CREATE TABLE IF NOT EXISTS contacts (
            id         SERIAL PRIMARY KEY,
            name       VARCHAR(200) NOT NULL DEFAULT '',
            phone      VARCHAR(60)  NOT NULL UNIQUE,
            notes      TEXT         NOT NULL DEFAULT '',
            created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        );
        CREATE TABLE IF NOT EXISTS messages_log (
            id          SERIAL PRIMARY KEY,
            session_id  VARCHAR(100) NOT NULL DEFAULT '',
            direction   VARCHAR(3)   NOT NULL DEFAULT 'out',
            from_number VARCHAR(100) NOT NULL DEFAULT '',
            to_number   VARCHAR(100) NOT NULL DEFAULT '',
            message     TEXT         NOT NULL DEFAULT '',
            media_type  VARCHAR(50)  NOT NULL DEFAULT 'text',
            status      VARCHAR(20)  NOT NULL DEFAULT 'sent',
            wa_msg_id   VARCHAR(200) NOT NULL DEFAULT '',
            created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        );
        CREATE TABLE IF NOT EXISTS broadcast_jobs (
            id           SERIAL PRIMARY KEY,
            session_id   VARCHAR(100) NOT NULL DEFAULT '',
            message      TEXT         NOT NULL DEFAULT '',
            recipients   TEXT         NOT NULL DEFAULT '[]',
            sent_count   INT          NOT NULL DEFAULT 0,
            failed_count INT          NOT NULL DEFAULT 0,
            status       VARCHAR(20)  NOT NULL DEFAULT 'done',
            created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        );
        CREATE TABLE IF NOT EXISTS autoreply_rules (
            id         SERIAL PRIMARY KEY,
            keyword    VARCHAR(500) NOT NULL DEFAULT '',
            reply      TEXT         NOT NULL DEFAULT '',
            is_regex   BOOLEAN      NOT NULL DEFAULT FALSE,
            enabled    BOOLEAN      NOT NULL DEFAULT TRUE,
            created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        );
        CREATE TABLE IF NOT EXISTS app_settings (
            key   VARCHAR(100) PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        );
        INSERT INTO app_settings (key, value) VALUES ('device_name', 'Integrasi-wa.jodyaryono.id') ON CONFLICT (key) DO NOTHING;
        INSERT INTO app_settings (key, value) VALUES ('browser_name', 'Google Chrome') ON CONFLICT (key) DO NOTHING;
    `);
    console.log('[DB] Tables ready');
}

// ─── MULTI-SESSION STATE ──────────────────────────────────────────────────────
const sessions = new Map();

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function sanitizeId(id) { return String(id).replace(/[^a-zA-Z0-9_-]/g, ''); }
function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
function getFirstOpenSession() {
    for (const [id, s] of sessions) if (s.status === 'open') return id;
    return '';
}
function numberToJid(number) {
    let num = number.replace(/\D/g, '');
    if (num.startsWith('0')) num = '62' + num.slice(1);
    return num + '@c.us';
}
function resolveGroupJid(nameOrJid, groupCache) {
    if (nameOrJid.endsWith('@g.us')) return nameOrJid;
    for (const [jid, meta] of groupCache) {
        if (meta.subject?.toLowerCase() === nameOrJid.toLowerCase()) return jid;
    }
    for (const [jid, meta] of groupCache) {
        if (meta.subject?.toLowerCase().includes(nameOrJid.toLowerCase())) return jid;
    }
    return null;
}
function getSessionAuthDir(phoneId) { return path.join('./auth_info', phoneId); }
async function getAppSettings() {
    const { rows } = await db.query('SELECT key, value FROM app_settings');
    const settings = {};
    for (const r of rows) settings[r.key] = r.value;
    return settings;
}
async function saveAppSetting(key, value) {
    await db.query('INSERT INTO app_settings (key, value) VALUES ($1, $2) ON CONFLICT (key) DO UPDATE SET value=$2', [key, value]);
}
function getContentType(msg) {
    if (msg.hasMedia) {
        if (msg.type === 'image') return 'imageMessage';
        if (msg.type === 'video') return 'videoMessage';
        if (msg.type === 'audio' || msg.type === 'ptt') return 'audioMessage';
        if (msg.type === 'document') return 'documentMessage';
        if (msg.type === 'sticker') return 'stickerMessage';
        return msg.type + 'Message';
    }
    return 'conversation';
}

// ─── WEBHOOK ──────────────────────────────────────────────────────────────────
async function forwardToWebhook(msg, phoneId, groupCache) {
    const jid = msg.from;
    const isGroup = jid?.endsWith('@g.us');
    const contentType = getContentType(msg);
    const textContent = msg.body || '';
    const fromNum = isGroup ? (msg.author || '').replace('@c.us', '') : jid.replace('@c.us', '');
    // Log to DB
    try {
        await db.query('INSERT INTO messages_log(session_id,direction,from_number,to_number,message,media_type,status,wa_msg_id) VALUES($1,$2,$3,$4,$5,$6,$7,$8)',
            [phoneId, 'in', fromNum, phoneId, textContent, contentType?.replace('Message', '') || 'text', 'received', msg.id?._serialized || '']);
    } catch { }
    // Autoreply check
    try {
        if (textContent) {
            const { rows: rules } = await db.query('SELECT * FROM autoreply_rules WHERE enabled=TRUE ORDER BY id');
            const sess = sessions.get(phoneId);
            if (sess?.client && sess.status === 'open') {
                for (const rule of rules) {
                    let matched = false;
                    if (rule.is_regex) { try { matched = new RegExp(rule.keyword, 'i').test(textContent); } catch { } }
                    else { matched = textContent.toLowerCase().includes(rule.keyword.toLowerCase()); }
                    if (matched) {
                        await sess.client.sendMessage(jid, rule.reply);
                        await db.query('INSERT INTO messages_log(session_id,direction,from_number,to_number,message,media_type,status) VALUES($1,$2,$3,$4,$5,$6,$7)',
                            [phoneId, 'out', phoneId, fromNum, rule.reply, 'text', 'autoreply']);
                        break;
                    }
                }
            }
        }
    } catch { }
    // Per-session webhook
    const sessObj = sessions.get(phoneId);
    const wUrl = sessObj?.webhookUrl || WEBHOOK_URL;
    const wEnabled = sessObj?.webhookEnabled !== undefined ? sessObj.webhookEnabled : WEBHOOK_ENABLED;
    if (!wUrl || !wEnabled) return;
    try {
        const contact = await msg.getContact();
        const pushName = contact?.pushname || null;
        const payload = {
            phone_id: phoneId, message_id: msg.id?._serialized, message: textContent,
            type: contentType?.replace('Message', '') || 'text',
            timestamp: msg.timestamp || Math.floor(Date.now() / 1000),
            sender: fromNum, sender_name: pushName, from: jid.replace('@c.us', '').replace('@g.us', ''),
            pushname: pushName,
            ...(isGroup ? { group_id: jid, from_group: jid, group_name: groupCache.get(jid)?.subject || jid } : {}),
            _key: { remoteJid: jid, id: msg.id?._serialized, fromMe: msg.fromMe || false, participant: msg.author || undefined },
        };
        const resp = await fetch(wUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        console.log('[Webhook][' + phoneId + '] ' + resp.status);
    } catch (e) { console.error('[Webhook][' + phoneId + ']', e.message); }
}

// ─── SESSION MANAGEMENT ───────────────────────────────────────────────────────
async function startSession(phoneId, label = '', apiToken = '', webhookUrl = '', webhookEnabled = false) {
    const id = sanitizeId(phoneId);
    if (!id) return;
    const existing = sessions.get(id);
    if (existing?.status === 'open') return;
    if (existing?.status === 'connecting' && existing?.client) return;
    if (existing?._reconnectTimer) { clearTimeout(existing._reconnectTimer); existing._reconnectTimer = null; }
    if (existing?.client) { try { await existing.client.destroy(); } catch { } existing.client = null; }

    const sess = { client: null, qrCode: null, qrDataUrl: null, status: 'connecting', groupCache: existing?.groupCache || new Map(), label: label || existing?.label || id, apiToken: apiToken || existing?.apiToken || '', webhookUrl: webhookUrl || existing?.webhookUrl || '', webhookEnabled: webhookEnabled !== undefined ? webhookEnabled : (existing?.webhookEnabled || false), _reconnectTimer: null, _failCount: existing?._failCount || 0 };
    sessions.set(id, sess);

    const puppeteerArgs = ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--no-first-run', '--no-zygote'];
    const appSettings = await getAppSettings();
    const client = new Client({
        authStrategy: new LocalAuth({ clientId: id, dataPath: './auth_info' }),
        puppeteer: { headless: true, args: puppeteerArgs, ...(CHROME_PATH ? { executablePath: CHROME_PATH } : {}) },
        deviceName: appSettings.device_name || 'Integrasi-wa.jodyaryono.id',
        browserName: appSettings.browser_name || 'Google Chrome',
    });
    sess.client = client;

    client.on('qr', async (qr) => {
        sess.qrCode = qr;
        sess.qrDataUrl = await QRCode.toDataURL(qr);
        sess.status = 'connecting';
        console.log('[WA][' + id + '] QR ready');
    });
    client.on('ready', async () => {
        sess.status = 'open';
        sess.qrCode = null;
        sess.qrDataUrl = null;
        sess._failCount = 0;
        console.log('[WA][' + id + '] Connected');
        refreshGroupCacheForSession(id);
    });
    client.on('authenticated', () => { console.log('[WA][' + id + '] Authenticated'); });
    client.on('auth_failure', (msg) => { console.error('[WA][' + id + '] Auth failure:', msg); sess.status = 'disconnected'; sess.client = null; });
    client.on('disconnected', (reason) => {
        console.log('[WA][' + id + '] Disconnected:', reason);
        sess.status = 'disconnected';
        sess.client = null;
        if (reason === 'LOGOUT') return;
        const sessionDir = path.join('./auth_info', 'session-' + id);
        if (!fs.existsSync(sessionDir)) { console.log('[WA][' + id + '] No session dir, not retrying.'); return; }
        sess._failCount = (sess._failCount || 0) + 1;
        const delay = sess._failCount >= 3 ? 5 * 60 * 1000 : 20000;
        console.log('[WA][' + id + '] Retry in ' + (delay / 1000) + 's (attempt ' + sess._failCount + ')');
        sess._reconnectTimer = setTimeout(() => { sess._reconnectTimer = null; startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled); }, delay);
    });
    client.on('message', async (msg) => {
        if (!msg.fromMe) await forwardToWebhook(msg, id, sess.groupCache);
    });
    client.on('group_update', async (notification) => {
        try {
            const chat = await client.getChatById(notification.chatId);
            if (chat.isGroup) sess.groupCache.set(notification.chatId, { subject: chat.name, participants: chat.participants });
        } catch { }
    });
    try {
        await client.initialize();
    } catch (e) {
        console.error('[WA][' + id + '] Initialize error:', e.message);
        sess.status = 'disconnected';
        sess.client = null;
        // Clean up corrupted auth to avoid repeated failures
        if (e.message?.includes('Protocol error') || e.message?.includes('Session closed')) {
            try { fs.rmSync(path.join('./auth_info', 'session-' + id), { recursive: true, force: true }); } catch { }
            try { fs.rmSync(getSessionAuthDir(id), { recursive: true, force: true }); } catch { }
            console.log('[WA][' + id + '] Cleaned corrupted auth data');
        }
    }
}

async function refreshGroupCacheForSession(phoneId) {
    const sess = sessions.get(phoneId);
    if (!sess?.client) return;
    try {
        const chats = await sess.client.getChats();
        const groups = chats.filter(c => c.isGroup);
        for (const g of groups) sess.groupCache.set(g.id._serialized, { subject: g.name, participants: g.participants || [] });
        console.log('[WA][' + phoneId + '] Cached ' + sess.groupCache.size + ' groups');
    } catch (e) { console.error('[WA][' + phoneId + '] Group fetch:', e.message); }
}

async function removeSession(phoneId) {
    const id = sanitizeId(phoneId);
    const sess = sessions.get(id);
    if (sess?.client) { try { await sess.client.logout(); } catch { } try { await sess.client.destroy(); } catch { } }
    sessions.delete(id);
    try { fs.rmSync(path.join('./auth_info', 'session-' + id), { recursive: true, force: true }); } catch { }
    try { fs.rmSync(getSessionAuthDir(id), { recursive: true, force: true }); } catch { }
    try { await db.query('DELETE FROM wa_sessions WHERE phone_id=$1', [id]); } catch { }
}

async function loadSessionsFromDb() {
    try {
        const { rows } = await db.query('SELECT phone_id, label, api_token, webhook_url, webhook_enabled FROM wa_sessions ORDER BY created_at');
        for (const row of rows) {
            if (!row.api_token) {
                row.api_token = randomBytes(16).toString('hex');
                await db.query('UPDATE wa_sessions SET api_token=$1 WHERE phone_id=$2', [row.api_token, row.phone_id]);
            }
            const sessionDir = path.join('./auth_info', 'session-' + sanitizeId(row.phone_id));
            if (fs.existsSync(sessionDir)) {
                console.log('[Gateway] Restoring: ' + row.phone_id);
                startSession(row.phone_id, row.label, row.api_token, row.webhook_url || '', row.webhook_enabled || false);
            } else {
                console.log('[Gateway] Idle (no session): ' + row.phone_id);
                sessions.set(sanitizeId(row.phone_id), { client: null, qrCode: null, qrDataUrl: null, status: 'disconnected', groupCache: new Map(), label: row.label || row.phone_id, apiToken: row.api_token, webhookUrl: row.webhook_url || '', webhookEnabled: row.webhook_enabled || false, _reconnectTimer: null });
            }
        }
    } catch (e) { console.error('[DB] Load sessions failed:', e.message); }
}

// ─── EXPRESS ──────────────────────────────────────────────────────────────────
const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(session({ secret: SESSION_SECRET, resave: false, saveUninitialized: false, cookie: { secure: false, maxAge: 1000 * 60 * 60 * 8 } }));

function apiAuth(req, res, next) {
    if (!AUTH_TOKEN) return next();
    if (req.session && req.session.loggedIn) return next(); // allow web session
    const header = req.headers.authorization || '';
    const token = header.startsWith('Bearer ') ? header.slice(7) : req.body?.token || req.query?.token;
    if (token === AUTH_TOKEN) return next();
    return res.status(401).json({ error: 'Unauthorized' });
}
function requireLogin(req, res, next) {
    if (req.session?.loggedIn) return next();
    if (req.headers.accept?.includes('application/json') || req.headers['content-type']?.includes('application/json'))
        return res.status(401).json({ error: 'Not logged in' });
    return res.redirect('/login');
}

// ─── CSS ──────────────────────────────────────────────────────────────────────
const CSS = `
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f3f4f6;color:#1f2937;min-height:100vh;}
a{color:inherit;text-decoration:none;}
/* Sidebar */
.sidebar{position:fixed;top:0;left:0;width:220px;height:100vh;background:#064e3b;display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
.sidebar-logo{padding:16px 14px 14px;border-bottom:1px solid rgba(255,255,255,.1);}
.sidebar-logo h1{font-size:.95rem;font-weight:700;color:#ecfdf5;display:flex;align-items:center;gap:8px;}
.sidebar-logo p{font-size:.62rem;color:#6ee7b7;margin-top:2px;letter-spacing:.06em;text-transform:uppercase;}
.sidebar-nav{padding:10px 8px;flex:1;display:flex;flex-direction:column;gap:4px;}
/* Top items (Dashboard / Logout) */
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:8px;color:#a7f3d0;font-size:.82rem;font-weight:500;cursor:pointer;transition:.15s;border:none;background:none;width:100%;text-align:left;text-decoration:none;}
.nav-item:hover{background:rgba(255,255,255,.08);color:#ecfdf5;}
.nav-item.active{background:#065f46;color:#ecfdf5;}
.nav-item .ni{font-size:.95rem;width:18px;text-align:center;flex-shrink:0;}
/* Nav cards (grouped menus) */
.nav-card{background:rgba(255,255,255,.06);border-radius:9px;overflow:hidden;margin-top:2px;}
.nav-card-title{font-size:.59rem;font-weight:700;color:#6ee7b7;text-transform:uppercase;letter-spacing:.1em;padding:8px 11px 4px;}
.nav-card-item{display:flex;align-items:center;gap:9px;padding:7px 11px;color:#a7f3d0;font-size:.8rem;font-weight:500;cursor:pointer;transition:.15s;width:100%;text-align:left;background:none;border:none;text-decoration:none;}
.nav-card-item:hover{background:rgba(255,255,255,.08);color:#ecfdf5;}
.nav-card-item.active{background:rgba(255,255,255,.12);color:#ecfdf5;}
.nav-card-item .ni{font-size:.88rem;width:17px;text-align:center;flex-shrink:0;}
.sidebar-footer{padding:10px 8px;border-top:1px solid rgba(255,255,255,.1);}
.user-info{display:flex;align-items:center;gap:9px;padding:9px 10px;background:rgba(255,255,255,.06);border-radius:8px;}
.user-avatar{width:28px;height:28px;background:#10b981;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:#fff;flex-shrink:0;}
.user-name{font-size:.78rem;font-weight:600;color:#ecfdf5;}
.user-role{font-size:.65rem;color:#6ee7b7;}
/* Main */
.main{margin-left:220px;min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#fff;border-bottom:1px solid #e5e7eb;padding:0 28px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
.topbar-title{font-size:.98rem;font-weight:600;color:#1f2937;}
.topbar-sub{font-size:.76rem;color:#9ca3af;margin-top:1px;}
.content{padding:26px 28px;flex:1;}
/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:18px;margin-bottom:24px;}
.stat-card{background:#fff;border-radius:12px;padding:18px;border:1px solid #e5e7eb;display:flex;align-items:center;gap:14px;box-shadow:0 1px 3px rgba(0,0,0,.05);}
.stat-icon{width:46px;height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
.si-green{background:#d1fae5;} .si-yellow{background:#fef3c7;} .si-red{background:#fee2e2;} .si-blue{background:#dbeafe;}
.stat-val{font-size:1.75rem;font-weight:700;color:#1f2937;line-height:1;}
.stat-lbl{font-size:.78rem;color:#6b7280;margin-top:3px;}
/* Card */
.card{background:#fff;border-radius:12px;border:1px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,.05);overflow:hidden;margin-bottom:22px;}
.card-header{padding:16px 22px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;}
.card-title{font-size:.9rem;font-weight:600;color:#1f2937;display:flex;align-items:center;gap:7px;}
.card-body{padding:22px;}
/* Table */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead th{padding:11px 16px;text-align:left;font-size:.71rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;background:#f9fafb;border-bottom:1px solid #e5e7eb;white-space:nowrap;}
tbody td{padding:14px 16px;border-bottom:1px solid #f3f4f6;font-size:.875rem;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover{background:#fafafa;}
.empty-row td{text-align:center;padding:48px;color:#9ca3af;font-size:.875rem;}
/* Badges */
.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:.71rem;font-weight:600;white-space:nowrap;}
.badge-open{background:#d1fae5;color:#065f46;} .badge-conn{background:#fef3c7;color:#92400e;} .badge-disc{background:#fee2e2;color:#991b1b;}
.bdot{width:6px;height:6px;border-radius:50%;display:inline-block;}
.badge-open .bdot{background:#10b981;animation:pulse 2s infinite;} .badge-conn .bdot{background:#f59e0b;animation:pulse 1s infinite;} .badge-disc .bdot{background:#ef4444;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.4;}}
/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-size:.83rem;font-weight:600;transition:.15s;white-space:nowrap;line-height:1;}
.btn:disabled{opacity:.5;cursor:not-allowed;}
.btn-sm{padding:5px 11px;font-size:.78rem;}
.btn-primary{background:#10b981;color:#fff;} .btn-primary:hover:not(:disabled){background:#059669;}
.btn-danger{background:#ef4444;color:#fff;} .btn-danger:hover:not(:disabled){background:#dc2626;}
.btn-ghost{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;} .btn-ghost:hover:not(:disabled){background:#e5e7eb;}
.btn-outline{background:transparent;color:#374151;border:1px solid #e5e7eb;} .btn-outline:hover:not(:disabled){background:#f9fafb;}
/* Form */
.form-group{margin-bottom:16px;}
label{display:block;margin-bottom:5px;font-size:.82rem;font-weight:500;color:#374151;}
.hint{font-weight:400;color:#9ca3af;font-size:.76rem;}
.input{width:100%;padding:9px 13px;border-radius:8px;border:1px solid #d1d5db;font-size:.875rem;color:#1f2937;outline:none;transition:border-color .15s;background:#fff;}
.input:focus{border-color:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.1);}
.input::placeholder{color:#9ca3af;}
.form-row{display:grid;grid-template-columns:1fr 1fr auto;gap:14px;align-items:end;}
/* Toast */
#toast-box{position:fixed;top:18px;right:18px;z-index:999;display:flex;flex-direction:column;gap:9px;}
.toast{padding:11px 16px;border-radius:10px;font-size:.85rem;font-weight:500;box-shadow:0 4px 14px rgba(0,0,0,.15);animation:fadeSlide .25s ease;display:flex;align-items:center;gap:9px;min-width:240px;max-width:360px;}
.toast-ok{background:#064e3b;color:#ecfdf5;} .toast-err{background:#7f1d1d;color:#fef2f2;}
@keyframes fadeSlide{from{transform:translateX(80px);opacity:0;}to{transform:none;opacity:1;}}
/* Spinner */
.spin{width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:rot .6s linear infinite;display:inline-block;}
@keyframes rot{to{transform:rotate(360deg);}}
/* Live dot */
.ldot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#10b981;animation:pulse 2s infinite;}
.live-txt{font-size:.73rem;color:#9ca3af;display:flex;align-items:center;gap:5px;}
/* Login */
.lp{background:linear-gradient(135deg,#064e3b 0%,#065f46 45%,#059669 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.lcard{background:#fff;border-radius:16px;padding:38px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.22);}
.llogo{text-align:center;margin-bottom:26px;}
.llogo .ico{font-size:2.4rem;display:block;margin-bottom:8px;}
.llogo h1{font-size:1.35rem;font-weight:700;color:#1f2937;}
.llogo p{font-size:.83rem;color:#6b7280;margin-top:3px;}
.alert-err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:11px 14px;font-size:.85rem;margin-bottom:14px;}
/* Number label cell */
.nlabel strong{font-size:.875rem;color:#1f2937;display:block;}
.nlabel span{font-size:.73rem;color:#9ca3af;font-family:monospace;}
/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:900;align-items:center;justify-content:center;backdrop-filter:blur(3px);}
.modal-overlay.open{display:flex;animation:mfade .2s ease;}
@keyframes mfade{from{opacity:0;}to{opacity:1;}}
.modal-box{background:#fff;border-radius:16px;padding:32px 28px;width:100%;max-width:420px;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.22);position:relative;animation:mslide .2s ease;}
@keyframes mslide{from{transform:translateY(-24px);opacity:0;}to{transform:none;opacity:1;}}
.modal-close{position:absolute;top:14px;right:16px;background:none;border:none;font-size:1.3rem;cursor:pointer;color:#9ca3af;line-height:1;}
.modal-close:hover{color:#374151;}
.qr-box{width:260px;height:260px;border-radius:12px;border:3px solid #e5e7eb;display:block;margin:16px auto;}
.qr-placeholder{width:260px;height:260px;border-radius:12px;border:3px dashed #e5e7eb;display:flex;align-items:center;justify-content:center;margin:16px auto;flex-direction:column;gap:10px;color:#9ca3af;font-size:.85rem;}
/* Responsive */
@media(max-width:768px){.sidebar{display:none;}.main{margin-left:0;}.content{padding:18px 14px;}}
`;

// ─── LAYOUT ───────────────────────────────────────────────────────────────────
function layout(title, breadcrumb, body, page) {
    if (page === undefined) page = 'device';
    const av = escHtml(ADMIN_USER.charAt(0).toUpperCase());
    const un = escHtml(ADMIN_USER);
    function ni(href, icon, label, key, target) {
        const cls = 'nav-item' + (page === key ? ' active' : '');
        const t = target ? ' target="' + target + '"' : '';
        return '    <a href="' + href + '" class="' + cls + '"' + t + '><span class="ni">' + icon + '</span> ' + label + '</a>\n';
    }
    function nci(href, icon, label, key, target) {
        const cls = 'nav-card-item' + (page === key ? ' active' : '');
        const t = target ? ' target="' + target + '"' : '';
        return '      <a href="' + href + '" class="' + cls + '"' + t + '><span class="ni">' + icon + '</span> ' + label + '</a>\n';
    }
    function ncGroup(title, items) {
        return '    <div class="nav-card">\n      <div class="nav-card-title">' + title + '</div>\n' + items + '    </div>\n';
    }
    return '<!DOCTYPE html>\n<html lang="id">\n<head>' +
        '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
        '<title>' + escHtml(title) + ' — Integrasi WA</title>' +
        '<style>' + CSS + '</style></head>\n<body>\n' +
        '<div id="toast-box"></div>\n' +
        '<aside class="sidebar">\n' +
        '  <div class="sidebar-logo"><h1>📱 Integrasi WA</h1><p>WhatsApp Gateway</p></div>\n' +
        '  <nav class="sidebar-nav">\n' +
        ni('/dashboard', '🏠', 'Dashboard', 'dashboard') +
        ni('/logout', '🚪', 'Logout', 'logout') +
        ncGroup('Contact',
            nci('#', '📞', 'Contact Numbers', 'contact-numbers') +
            nci('#', '👥', 'Grouping Numbers', 'grouping-numbers')
        ) +
        ncGroup('Outbox Message',
            nci('#', '💬', 'Send Message', 'send-message') +
            nci('#', '📢', 'Send Broadcast', 'send-broadcast') +
            nci('#', '📋', 'Broadcast History', 'broadcast-history') +
            nci('#', '👥', 'Send to Group', 'send-to-group') +
            nci('#', '🗂️', 'Messages History', 'messages-history')
        ) +
        ncGroup('Inbox Message',
            nci('#', '💻', 'Web Whatsapp', 'web-whatsapp')
        ) +
        ncGroup('Otomasi',
            nci('#', '🔄', 'Autoreply', 'autoreply')
        ) +
        ncGroup('Bill',
            nci('#', '🧾', 'Invoice', 'invoice')
        ) +
        ncGroup('Setting',
            nci('/dashboard', '📱', 'Device', 'device') +
            nci('/settings', '⚙️', 'General Settings', 'general-settings') +
            nci('/api-manual', '📖', 'API Manual', 'api-manual')
        ) +
        '  </nav>\n' +
        '  <div class="sidebar-footer">\n' +
        '    <div class="user-info">\n' +
        '      <div class="user-avatar">' + av + '</div>\n' +
        '      <div><div class="user-name">' + un + '</div><div class="user-role">Administrator</div></div>\n' +
        '    </div>\n' +
        '  </div>\n' +
        '</aside>\n' +
        '<div class="main">\n' +
        '  <header class="topbar">\n' +
        '    <div><div class="topbar-title">' + escHtml(title) + '</div><div class="topbar-sub">' + escHtml(breadcrumb) + '</div></div>\n' +
        '    <div class="live-txt"><span class="ldot"></span> Live updates aktif</div>\n' +
        '  </header>\n' +
        '  <div class="content">' + body + '</div>\n' +
        '</div>\n' +
        '<script>function toast(msg,t){const c=document.getElementById("toast-box");const el=document.createElement("div");el.className="toast "+(t==="err"?"toast-err":"toast-ok");el.textContent=msg;c.appendChild(el);setTimeout(()=>el.remove(),4000);}</script>\n' +
        '</body></html>';
}

// ─── LOGIN ────────────────────────────────────────────────────────────────────
app.get('/login', (req, res) => {
    if (req.session?.loggedIn) return res.redirect('/dashboard');
    const err = req.query.error ? '<div class="alert-err">❌ Username atau password salah.</div>' : '';
    res.send('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login — Integrasi WA</title><style>' + CSS + '</style></head><body><div class="lp"><div class="lcard"><div class="llogo"><span class="ico">📱</span><h1>Integrasi WA</h1><p>WhatsApp Gateway — Multi Nomor</p></div>' + err + '<form method="POST" action="/login"><div class="form-group"><label>Username</label><input class="input" type="text" name="username" placeholder="admin" autocomplete="username" required autofocus></div><div class="form-group"><label>Password</label><input class="input" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required></div><button class="btn btn-primary" style="width:100%;padding:11px;font-size:.95rem;justify-content:center;" type="submit">Masuk</button></form></div></div></body></html>');
});
app.post('/login', (req, res) => {
    const { username, password } = req.body;
    if (username === ADMIN_USER && password === ADMIN_PASS) { req.session.loggedIn = true; return res.redirect('/dashboard'); }
    return res.redirect('/login?error=1');
});
app.get('/logout', (req, res) => { req.session.destroy(() => res.redirect('/login')); });

// ─── DASHBOARD ────────────────────────────────────────────────────────────────
app.get('/dashboard', requireLogin, (req, res) => {
    const total = sessions.size;
    const connected = Array.from(sessions.values()).filter(s => s.status === 'open').length;
    const connecting = Array.from(sessions.values()).filter(s => s.status === 'connecting').length;
    const disc = total - connected - connecting;

    const stats =
        '<div class="stats-grid">' +
        '<div class="stat-card"><div class="stat-icon si-blue">📱</div><div><div class="stat-val" id="st-total">' + total + '</div><div class="stat-lbl">Total Nomor</div></div></div>' +
        '<div class="stat-card"><div class="stat-icon si-green">✅</div><div><div class="stat-val" id="st-conn">' + connected + '</div><div class="stat-lbl">Terhubung</div></div></div>' +
        '<div class="stat-card"><div class="stat-icon si-yellow">⏳</div><div><div class="stat-val" id="st-cing">' + connecting + '</div><div class="stat-lbl">Menghubungkan</div></div></div>' +
        '<div class="stat-card"><div class="stat-icon si-red">❌</div><div><div class="stat-val" id="st-disc">' + disc + '</div><div class="stat-lbl">Terputus</div></div></div>' +
        '</div>';

    const table =
        '<div class="card">' +
        '<div class="card-header"><span class="card-title">📋 Daftar Nomor WhatsApp</span>' +
        '<div style="display:flex;align-items:center;gap:12px;">' +
        '<span class="live-txt"><span class="ldot"></span> Refresh otomatis</span>' +
        '<button class="btn btn-primary btn-sm" onclick="openAddModal()">➕ Tambah Device</button>' +
        '</div></div>' +
        '<div class="tbl-wrap"><table>' +
        '<thead><tr><th style="width:27%">Nomor / Label</th><th style="width:13%">Status</th><th style="width:9%">Grup</th><th style="width:22%">Token API</th><th>Aksi</th></tr></thead>' +
        '<tbody id="sess-tbody"><tr class="empty-row"><td colspan="5">Memuat…</td></tr></tbody>' +
        '</table></div></div>';

    const js = '<script>' +
        'function esc(s){return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/\'/g,"&#039;");}' +
        'function openAddModal(){document.getElementById("add-modal").classList.add("open");setTimeout(()=>document.getElementById("f-id").focus(),80);}' +
        'function closeAddModal(){document.getElementById("add-modal").classList.remove("open");document.getElementById("add-form").reset();}' +
        'async function refresh(){' +
        '  try{const r=await fetch("/web/sessions");if(!r.ok)return;const d=await r.json();' +
        '  const ss=d.sessions;' +
        '  document.getElementById("st-total").textContent=ss.length;' +
        '  document.getElementById("st-conn").textContent=ss.filter(x=>x.status==="open").length;' +
        '  document.getElementById("st-cing").textContent=ss.filter(x=>x.status==="connecting").length;' +
        '  document.getElementById("st-disc").textContent=ss.filter(x=>x.status==="disconnected").length;' +
        '  const tb=document.getElementById("sess-tbody");' +
        '  if(!ss.length){tb.innerHTML=\'<tr class="empty-row"><td colspan="5">Belum ada nomor.</td></tr>\';return;}' +
        '  tb.innerHTML=ss.map(s=>{' +
        '    const bc=s.status==="open"?"badge-open":s.status==="connecting"?"badge-conn":"badge-disc";' +
        '    const bl=s.status==="open"?"Terhubung":s.status==="connecting"?"Menghubungkan":"Terputus";' +
        '    const qr=s.status!=="open"?(""+\'<button class="btn btn-ghost btn-sm" data-qrid="\'+esc(s.phone_id)+\'" onclick="openQR(this.dataset.qrid)">📷 Scan QR</button>\'+\'<button class="btn btn-ghost btn-sm" style="background:#f0f9ff;color:#0369a1;border-color:#bae6fd;" data-pairid="\'+esc(s.phone_id)+\'" onclick="openPairing(this.dataset.pairid)">🔑 Kode Pairing</button>\'):(\'<span style="color:#10b981;font-size:.8rem;">✅ Online</span><button class="btn btn-ghost btn-sm" style="background:#fef2f2;color:#dc2626;border-color:#fecaca;" data-discid="\'+esc(s.phone_id)+\'" onclick="disc(this.dataset.discid)">🔌 Disconnect</button>\');' +
        '    return "<tr>"' +
        '      +"<td><div class=\'nlabel\'><strong>"+esc(s.label)+"</strong><span>"+esc(s.phone_id)+"</span></div></td>"' +
        '      +"<td><span class=\'badge "+bc+"\'><span class=\'bdot\'></span>"+bl+"</span></td>"' +
        '      +"<td><button data-pid=\'"+esc(s.phone_id)+"\'  onclick=\'showGroups(this.dataset.pid)\'  style=\'background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;border-radius:7px;padding:2px 12px;font-size:.8rem;font-weight:700;cursor:pointer;\'>"+s.groups+" grup &#128065;</button></td>"' +
        '      +"<td><div style=\'display:flex;align-items:center;gap:5px;\'><code style=\'font-size:.70rem;background:#f3f4f6;padding:2px 7px;border-radius:4px;border:1px solid #e5e7eb;font-family:monospace;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:middle;\'>"+( s.api_token?s.api_token.substring(0,16)+\'…\':\'—\')+"</code>"+(s.api_token?"<button class=\'btn btn-ghost btn-sm\' style=\'padding:2px 7px;flex-shrink:0;\' data-tok=\'"+esc(s.api_token)+"\' onclick=\'copyTok(this.dataset.tok)\' title=\'Salin Token\'>📋</button>":"")+"</div></td>"' +
        '      +"<td style=\'display:flex;gap:7px;flex-wrap:wrap;align-items:center;\'>"+qr' +
        '      +"<button class=\'btn btn-ghost btn-sm\' style=\'background:#fefce8;color:#854d0e;border-color:#fde68a;\' data-histid=\'"+esc(s.phone_id)+"\' onclick=\'openHistory(this.dataset.histid)\'>📜 History</button>"' +
        '      +"<button class=\'btn btn-ghost btn-sm\' style=\'background:#f0fdf4;color:#065f46;border-color:#bbf7d0;\' onclick=\'openWebhook(\\""+esc(s.phone_id)+"\\",\\""+esc(s.webhook_url||"")+"\\",\\""+s.webhook_enabled+"\\")\'>🔗 Webhook</button>"' +
        '      +"<button class=\'btn btn-danger btn-sm\' onclick=\'del(\\""+esc(s.phone_id)+"\\")\'>🗑 Hapus</button></td>"' +
        '      +"</tr>";' +
        '  }).join("");' +
        '  }catch(e){console.error(e);}' +
        '}' +
        'async function del(id){' +
        '  if(!confirm("Hapus sesi \\""+id+"\\"? Koneksi WA akan diputus."))return;' +
        '  const r=await fetch("/web/session/"+encodeURIComponent(id)+"/delete",{method:"POST",headers:{"Content-Type":"application/json"}});' +
        '  const d=await r.json();' +
        '  if(d.success){toast("Sesi "+id+" dihapus");refresh();}else toast(d.error||"Gagal","err");' +
        '}' +
        'async function disc(id){' +
        '  if(!confirm("Disconnect sesi \\""+id+"\\"? Perangkat akan terputus dari WhatsApp."))return;' +
        '  try{' +
        '    const r=await fetch("/web/session/"+encodeURIComponent(id)+"/disconnect",{method:"POST",headers:{"Content-Type":"application/json"}});' +
        '    const d=await r.json();' +
        '    if(d.success){toast("Sesi "+id+" disconnected");refresh();}else toast(d.error||"Gagal disconnect","err");' +
        '  }catch(e){toast("Disconnect diproses, refresh...");setTimeout(refresh,2000);}' +
        '}' +
        'document.getElementById("add-form").addEventListener("submit",async function(e){' +
        '  e.preventDefault();' +
        '  const id=document.getElementById("f-id").value.trim();' +
        '  const lb=document.getElementById("f-label").value.trim();' +
        '  if(!id)return;' +
        '  const btn=document.getElementById("add-btn");' +
        '  const txt=document.getElementById("add-txt");' +
        '  btn.disabled=true;txt.innerHTML=\'<span class="spin"></span> Menyimpan…\';' +
        '  try{' +
        '    const r=await fetch("/web/session/add",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({phoneId:id,label:lb})});' +
        '    const d=await r.json();' +
        '    if(d.success){toast("Device "+id+" ditambahkan! Buka QR untuk scan.");closeAddModal();setTimeout(refresh,600);}' +
        '    else toast(d.error||"Gagal","err");' +
        '  }catch(err){toast("Error: "+err.message,"err");}' +
        '  btn.disabled=false;txt.textContent="➕ Tambah";' +
        '});' +
        'refresh();setInterval(refresh,5000);' +
        'function copyTok(tok){navigator.clipboard.writeText(tok).then(()=>toast("Token disalin! \u2713")).catch(()=>{const el=document.createElement("textarea");el.value=tok;document.body.appendChild(el);el.select();document.execCommand("copy");document.body.removeChild(el);toast("Token disalin! \u2713");});}' +
        'let _qrTimer=null,_qrId=null,_qrStart=0;' +
        'function openQR(id){' +
        '  _qrId=id;_qrStart=Date.now();' +
        '  const img=document.getElementById("qr-img");' +
        '  const ph=document.getElementById("qr-ph");' +
        '  img.style.display="none";img.src="";' +
        '  ph.style.display="flex";ph.innerHTML=\'<span class="spin" style="border-color:#d1d5db;border-top-color:#10b981;width:32px;height:32px;border-width:3px"></span><span>Memuat QR…</span>\';' +
        '  document.getElementById("qr-tip").textContent="Menghubungkan ke server…";' +
        '  document.getElementById("qr-label").textContent=id;' +
        '  document.getElementById("qr-modal").classList.add("open");' +
        '  clearInterval(_qrTimer);' +
        '  fetch("/web/session/"+encodeURIComponent(id)+"/restart",{method:"POST"}).then(()=>{loadQR();_qrTimer=setInterval(loadQR,8000);}).catch(()=>{loadQR();_qrTimer=setInterval(loadQR,8000);});' +
        '}' +
        'function closeQRModal(){' +
        '  document.getElementById("qr-modal").classList.remove("open");' +
        '  clearInterval(_qrTimer);_qrTimer=null;_qrId=null;' +
        '}' +
        'async function openPairing(id){' +
        '  if(!confirm("Dapatkan kode pairing untuk nomor "+id+"?\\n\\nSetelah kode muncul, masukkan di HP:\\nWhatsApp → ⋮ → Perangkat Tertaut → Tautkan dengan nomor telepon"))return;' +
        '  const overlay=document.createElement("div");' +
        '  overlay.id="pairing-overlay";' +
        '  overlay.style.cssText="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;display:flex;align-items:center;justify-content:center;";' +
        '  overlay.innerHTML=\'<div style="background:#fff;border-radius:14px;padding:36px 40px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.25);min-width:280px;">\' +' +
        '    \'<div style="font-size:2.2rem;margin-bottom:12px">⏳</div>\' +' +
        '    \'<div style="font-weight:700;font-size:1.1rem;margin-bottom:8px">Meminta Kode Pairing…</div>\' +' +
        '    \'<div style="color:#6b7280;font-size:.9rem">Menghubungkan ke WhatsApp.<br>Mohon tunggu hingga 120 detik.</div>\' +' +
        '    \'<div style="margin-top:18px;width:100%;height:4px;background:#e5e7eb;border-radius:99px;overflow:hidden;">\' +' +
        '      \'<div id="pair-progress" style="height:100%;width:0%;background:#10b981;border-radius:99px;transition:width 120s linear;"></div>\' +' +
        '    \'</div>\' +' +
        '  \'</div>\';' +
        '  document.body.appendChild(overlay);' +
        '  setTimeout(()=>{const p=document.getElementById("pair-progress");if(p)p.style.width="100%";},50);' +
        '  try{' +
        '    const r=await fetch("/web/session/"+encodeURIComponent(id)+"/pairing-code",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({phone:id})});' +
        '    const d=await r.json();' +
        '    overlay.remove();' +
        '    if(d.code){' +
        '      const box=document.createElement("div");' +
        '      box.id="pairing-result-overlay";' +
        '      box.style.cssText="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;display:flex;align-items:center;justify-content:center;";' +
        '      box.innerHTML=\'<div style="background:#fff;border-radius:14px;padding:36px 40px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.25);min-width:300px;max-width:400px;">\' +' +
        '        \'<div style="font-size:2.2rem;margin-bottom:12px">🔑</div>\' +' +
        '        \'<div style="font-weight:700;font-size:1.1rem;margin-bottom:14px">Kode Pairing</div>\' +' +
        '        \'<div id="pair-code-val" style="font-size:2rem;font-weight:800;letter-spacing:.3em;background:#f0fdf4;color:#065f46;border:2px solid #bbf7d0;border-radius:10px;padding:14px 24px;margin-bottom:16px;font-family:monospace;">\'+esc(d.code)+\'</div>\' +' +
        '        \'<div style="color:#6b7280;font-size:.85rem;margin-bottom:12px">WhatsApp → ⋮ → Perangkat Tertaut → Tautkan dengan nomor telepon</div>\' +' +
        '        \'<div style="display:flex;gap:10px;justify-content:center;margin-bottom:16px">\'  +' +
        '        \'<button id="pair-copy-btn" style="background:#0ea5e9;color:#fff;border:none;border-radius:8px;padding:8px 20px;font-size:.9rem;font-weight:600;cursor:pointer;">📋 Copy Kode</button>\' +' +
        '        \'</div>\' +' +
        '        \'<div id="pair-status-msg" style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;color:#92400e;font-size:.82rem;font-weight:600;margin-bottom:16px">⏳ Menunggu pairing… Jangan tutup halaman ini.</div>\' +' +
        '        \'<button id="pair-close-btn" style="background:#6b7280;color:#fff;border:none;border-radius:8px;padding:10px 28px;font-size:1rem;font-weight:600;cursor:pointer;">Tutup</button>\' +' +
        '      \'</div>\';' +
        '      document.body.appendChild(box);' +
        '      document.getElementById("pair-copy-btn").addEventListener("click",function(){navigator.clipboard.writeText(d.code.replace(/\\\\s/g,"")).then(()=>{this.textContent="\\u2705 Copied!";setTimeout(()=>{this.textContent="\\ud83d\\udccb Copy Kode";},1500);}).catch(()=>{});});' +
        '      document.getElementById("pair-close-btn").addEventListener("click",function(){clearInterval(window._pairPoll);box.remove();});' +
        '      window._pairPoll=setInterval(async()=>{' +
        '        try{const pr=await fetch("/web/qr/"+encodeURIComponent(id));const pd=await pr.json();' +
        '          if(pd.status==="open"){clearInterval(window._pairPoll);' +
        '            const bx=document.getElementById("pairing-result-overlay");if(!bx)return;' +
        '            bx.querySelector("div > div").innerHTML=' +
        '              \'<div style="font-size:3.5rem;margin-bottom:12px">✅</div>\'+' +
        '              \'<div style="font-weight:700;font-size:1.2rem;color:#065f46;margin-bottom:8px">Pairing Berhasil!</div>\'+' +
        '              \'<div style="color:#6b7280;font-size:.9rem;margin-bottom:20px">Nomor \'+esc(id)+\' berhasil terhubung ke WhatsApp.</div>\'+' +
        '              \'<button onclick="document.getElementById(\\\'pairing-result-overlay\\\').remove();" style="background:#10b981;color:#fff;border:none;border-radius:8px;padding:10px 28px;font-size:1rem;font-weight:600;cursor:pointer;">OK</button>\';' +
        '            loadSessions();}' +
        '        }catch(e){}' +
        '      },2000);' +
        '    } else {' +
        '      alert("❌ "+(d.error||"Gagal mendapatkan kode pairing"));' +
        '    }' +
        '  }catch(e){overlay.remove();alert("Error: "+e.message);}' +
        '}' +
        'async function copyQR(){' +
        '  const img=document.getElementById("qr-img");if(!img||!img.src||img.style.display==="none")return;' +
        '  try{const r=await fetch(img.src);const b=await r.blob();await navigator.clipboard.write([new ClipboardItem({"image/png":b})]);' +
        '    const btn=document.getElementById("qr-copy-btn");btn.textContent="\\u2705 Copied!";setTimeout(()=>{btn.textContent="\\ud83d\\udccb Copy QR";},1500);' +
        '  }catch(e){try{await navigator.clipboard.writeText(img.src);const btn=document.getElementById("qr-copy-btn");btn.textContent="\\u2705 Copied!";setTimeout(()=>{btn.textContent="\\ud83d\\udccb Copy QR";},1500);}catch(e2){}}' +
        '}' +
        'async function loadQR(){' +
        '  if(!_qrId)return;' +
        '  try{' +
        '    const r=await fetch("/web/qr/"+encodeURIComponent(_qrId));' +
        '    if(r.status===401){closeQRModal();location.href="/login";return;}' +
        '    const d=await r.json();' +
        '    document.getElementById("qr-label").textContent=d.label||_qrId;' +
        '    const img=document.getElementById("qr-img");' +
        '    const ph=document.getElementById("qr-ph");' +
        '    const tip=document.getElementById("qr-tip");' +
        '    if(d.status==="open"){' +
        '      img.style.display="none";ph.style.display="flex";' +
        '      ph.innerHTML=\'<span style="font-size:3rem">✅</span><span style="font-weight:600;color:#065f46">Terhubung!</span>\';' +
        '      tip.textContent="Nomor berhasil terhubung ke WhatsApp.";' +
        '      clearInterval(_qrTimer);setTimeout(()=>{closeQRModal();refresh();},2500);' +
        '    } else if(d.qr){' +
        '      img.src=d.qr;img.style.display="block";ph.style.display="none";' +
        '      tip.textContent="Buka WhatsApp → Menu → Perangkat Tertaut → Tautkan Perangkat";' +
        '    } else {' +
        '      const elapsed=Date.now()-_qrStart;' +
        '      if(d.status==="disconnected"&&elapsed>5000){' +
        '        img.style.display="none";ph.style.display="flex";' +
        '        ph.innerHTML=\'<span style="font-size:2.2rem">⚠️</span><span style="color:#b45309;font-weight:600;text-align:center">Gagal terhubung ke WhatsApp</span>\';' +
        '        tip.textContent="Server WA menolak koneksi (kemungkinan rate limit). Tutup dan coba lagi beberapa menit lagi.";' +
        '        clearInterval(_qrTimer);' +
        '      } else if(elapsed>60000){' +
        '        img.style.display="none";ph.style.display="flex";' +
        '        ph.innerHTML=\'<span style="font-size:2.2rem">⏱️</span><span style="color:#b45309;font-weight:600;text-align:center">Timeout — QR tidak muncul</span>\';' +
        '        tip.textContent="WA tidak menghasilkan QR dalam 60 detik. Tutup dan coba Scan QR lagi.";' +
        '        clearInterval(_qrTimer);' +
        '      } else {' +
        '        img.style.display="none";ph.style.display="flex";' +
        '        ph.innerHTML=\'<span class="spin" style="border-color:#d1d5db;border-top-color:#10b981;width:32px;height:32px;border-width:3px"></span><span>Menghubungkan ke WhatsApp…</span>\';' +
        '        tip.textContent="QR sedang dibuat, harap tunggu…";' +
        '      }' +
        '    }' +
        '  }catch(e){console.error(e);}' +
        '}' +
        'document.addEventListener("click",function(e){if(e.target&&e.target.id==="qr-modal")closeQRModal();if(e.target&&e.target.id==="add-modal")closeAddModal();if(e.target&&e.target.id==="wh-modal")closeWebhook();if(e.target&&e.target.id==="hist-modal")closeHistory();});' +
        'let _histId=null,_histPage=1;' +
        'function openHistory(id){_histId=id;_histPage=1;document.getElementById("hist-modal-id").textContent=id;document.getElementById("hist-body").innerHTML="";document.getElementById("hist-modal").classList.add("open");loadHistory();}' +
        'function closeHistory(){document.getElementById("hist-modal").classList.remove("open");_histId=null;}' +
        'async function loadHistory(dir){' +
        '  if(!_histId)return;' +
        '  const q=new URLSearchParams({page:_histPage,limit:20});if(dir)q.set("dir",dir);' +
        '  document.getElementById("hist-body").innerHTML=\'<tr><td colspan="6" style="text-align:center;padding:20px"><span class="spin" style="display:inline-block;margin-right:8px"></span>Memuat...</td></tr>\';' +
        '  try{const r=await fetch("/web/session/"+encodeURIComponent(_histId)+"/history?"+q);const d=await r.json();' +
        '    if(!d.rows||!d.rows.length){document.getElementById("hist-body").innerHTML=\'<tr><td colspan="6" style="text-align:center;padding:20px;color:#9ca3af">Belum ada pesan.</td></tr>\';document.getElementById("hist-pager").innerHTML="";return;}' +
        '    document.getElementById("hist-body").innerHTML=d.rows.map(r=>{' +
        '      const dir=r.direction==="in"?\'<span style="background:#ecfdf5;color:#065f46;padding:2px 8px;border-radius:4px;font-size:.72rem;font-weight:600">📥 Masuk</span>\':\'<span style="background:#fef2f2;color:#991b1b;padding:2px 8px;border-radius:4px;font-size:.72rem;font-weight:600">📤 Keluar</span>\';' +
        '      const num=r.direction==="in"?r.from_number:r.to_number;' +
        '      return "<tr><td style=\'font-size:.75rem;color:#6b7280;white-space:nowrap\'>"+new Date(r.created_at).toLocaleString("id-ID")+"</td><td>"+dir+"</td><td style=\'font-family:monospace;font-size:.78rem\'>"+esc(num)+"</td><td style=\'font-size:.78rem\'>"+esc(r.media_type)+"</td><td style=\'max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.78rem\'>"+esc(r.message)+"</td><td style=\'font-size:.75rem;color:#6b7280\'>"+esc(r.status)+"</td></tr>";' +
        '    }).join("");' +
        '    const tp=Math.ceil(d.total/20);' +
        '    document.getElementById("hist-pager").innerHTML=(_histPage>1?\'<button class="btn btn-ghost btn-sm" onclick="_histPage--;loadHistory()">← Prev</button>\':"")' +
        '      +\'<span style="line-height:32px;font-size:.82rem;color:#6b7280">\'+_histPage+\' / \'+tp+\'</span>\'' +
        '      +(_histPage<tp?\'<button class="btn btn-ghost btn-sm" onclick="_histPage++;loadHistory()">Next →</button>\':"");' +
        '  }catch(e){document.getElementById("hist-body").innerHTML=\'<tr><td colspan="6" style="text-align:center;color:#ef4444">Error: \'+e.message+\'</td></tr>\';}' +
        '}' +
        'let _whId=null;' +
        'function openWebhook(id,url,en){_whId=id;document.getElementById("wh-modal-id").textContent=id;document.getElementById("wh-url-in").value=url||"";document.getElementById("wh-en-in").checked=(en==="true"||en===true);document.getElementById("wh-result").innerHTML="";document.getElementById("wh-modal").classList.add("open");}' +
        'function closeWebhook(){document.getElementById("wh-modal").classList.remove("open");_whId=null;}' +
        'async function saveWebhookModal(){if(!_whId)return;const url=document.getElementById("wh-url-in").value.trim();const en=document.getElementById("wh-en-in").checked;' +
        '  try{const r=await fetch("/web/session/"+encodeURIComponent(_whId)+"/webhook",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({webhook_url:url,webhook_enabled:en})});' +
        '    const d=await r.json();if(d.ok){toast("Webhook disimpan ✓");closeWebhook();refresh();}else toast(d.error||"Gagal","err");}' +
        '  catch(e){toast("Error: "+e.message,"err");}' +
        '}' +
        'async function testWebhookModal(){const url=document.getElementById("wh-url-in").value.trim();if(!url)return toast("Masukkan URL webhook dulu","err");' +
        '  const res=document.getElementById("wh-result");res.innerHTML=\'<span class="spin" style="display:inline-block;margin-right:6px"></span>Mengirim test...\';' +
        '  try{const r=await fetch("/web/webhook/test",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({url})});' +
        '    const d=await r.json();res.innerHTML=d.ok?\'<span style="color:#10b981">✅ Berhasil — HTTP \'+d.status+\'</span>\':\'<span style="color:#ef4444">❌ Gagal: \'+d.error+\'</span>\';' +
        '  }catch(e){res.innerHTML=\'<span style="color:#ef4444">❌ Error: \'+e.message+\'</span>\';}' +
        '}' +
        '</script>';

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

    const modal =
        '<div class="modal-overlay" id="qr-modal">' +
        '<div class="modal-box">' +
        '<button class="modal-close" onclick="closeQRModal()" title="Tutup">✕</button>' +
        '<div style="font-size:1.15rem;font-weight:700;margin-bottom:2px;">📲 Scan QR WhatsApp</div>' +
        '<div style="font-size:.82rem;color:#6b7280;margin-bottom:4px;" id="qr-label"></div>' +
        '<img id="qr-img" class="qr-box" src="" alt="QR Code" style="display:none">' +
        '<div id="qr-ph" class="qr-placeholder"></div>' +
        '<p style="font-size:.79rem;color:#6b7280;min-height:32px;margin-bottom:16px;padding:0 8px;" id="qr-tip"></p>' +
        '<div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">' +
        '<button class="btn btn-ghost btn-sm" onclick="loadQR()">🔄 Refresh QR</button>' +
        '<button class="btn btn-ghost btn-sm" id="qr-copy-btn" onclick="copyQR()" style="background:#f0f9ff;color:#0369a1;border-color:#bae6fd;">📋 Copy QR</button>' +
        '<button class="btn btn-danger btn-sm" onclick="closeQRModal()">✕ Tutup</button>' +
        '</div>' +
        '</div></div>' +
        '<div class="modal-overlay" id="add-modal">' +
        '<div class="modal-box" style="text-align:left;max-width:440px;">' +
        '<button class="modal-close" onclick="closeAddModal()" title="Tutup">✕</button>' +
        '<div style="font-size:1.1rem;font-weight:700;margin-bottom:18px;display:flex;align-items:center;gap:8px;">➕ Tambah Device WhatsApp</div>' +
        '<form id="add-form" autocomplete="off">' +
        '<div class="form-group"><label>ID Unik <span class="hint">(huruf/angka/strip, tanpa spasi)</span></label>' +
        '<input class="input" id="f-id" name="phoneId" placeholder="mis: 6281234567890 atau nomor1" pattern="[a-zA-Z0-9_\\-]+" required></div>' +
        '<div class="form-group"><label>Label <span class="hint">(nama tampilan)</span></label>' +
        '<input class="input" id="f-label" name="label" placeholder="mis: Nomor Utama CS"></div>' +
        '<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px;">' +
        '<button type="button" class="btn btn-ghost" onclick="closeAddModal()">Batal</button>' +
        '<button class="btn btn-primary" type="submit" id="add-btn"><span id="add-txt">➕ Tambah</span></button>' +
        '</div></form>' +
        '</div></div>' +
        '<div class="modal-overlay" id="wh-modal">' +
        '<div class="modal-box" style="text-align:left;max-width:480px">' +
        '<button class="modal-close" onclick="closeWebhook()" title="Tutup">✕</button>' +
        '<div style="font-size:1.1rem;font-weight:700;margin-bottom:4px">🔗 Webhook</div>' +
        '<div style="font-size:.8rem;color:#6b7280;margin-bottom:16px" id="wh-modal-id"></div>' +
        '<div class="form-group"><label>Webhook URL</label>' +
        '<input class="input" id="wh-url-in" placeholder="https://yourapp.com/webhook"></div>' +
        '<div class="form-group" style="display:flex;align-items:center;gap:10px">' +
        '<label style="margin:0;display:flex;align-items:center;gap:8px;cursor:pointer">' +
        '<input type="checkbox" id="wh-en-in" style="width:18px;height:18px"> Aktifkan Webhook</label></div>' +
        '<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:12px 0;font-size:.75rem">' +
        '<div style="font-weight:600;margin-bottom:6px">Format Payload (POST JSON)</div>' +
        '<pre style="margin:0;color:#374151;white-space:pre-wrap;word-break:break-all;overflow-wrap:break-word">{\n  &quot;phone_id&quot;: &quot;628xxx&quot;,\n  &quot;message&quot;: &quot;Halo!&quot;,\n  &quot;type&quot;: &quot;text&quot;,\n  &quot;timestamp&quot;: 1234567890,\n  &quot;sender&quot;: &quot;628xxx&quot;,\n  &quot;sender_name&quot;: &quot;John&quot;,\n  &quot;from&quot;: &quot;628xxx&quot;,\n  &quot;pushname&quot;: &quot;John&quot;\n}</pre></div>' +
        '<div style="display:flex;gap:8px;justify-content:flex-end">' +
        '<button class="btn btn-ghost" onclick="testWebhookModal()">🧪 Test</button>' +
        '<button class="btn btn-ghost" onclick="closeWebhook()">Batal</button>' +
        '<button class="btn btn-primary" onclick="saveWebhookModal()">💾 Simpan</button>' +
        '</div>' +
        '<div id="wh-result" style="margin-top:10px;font-size:.85rem"></div>' +
        '</div></div>';

    const histModal =
        '<div class="modal-overlay" id="hist-modal">' +
        '<div class="modal-box" style="text-align:left;max-width:820px;max-height:85vh;display:flex;flex-direction:column;">' +
        '<button class="modal-close" onclick="closeHistory()" title="Tutup">✕</button>' +
        '<div style="font-size:1.1rem;font-weight:700;margin-bottom:4px">📜 Riwayat Pesan</div>' +
        '<div style="font-size:.8rem;color:#6b7280;margin-bottom:12px" id="hist-modal-id"></div>' +
        '<div style="display:flex;gap:6px;margin-bottom:12px">' +
        '<button class="btn btn-sm btn-ghost" onclick="loadHistory()">Semua</button>' +
        '<button class="btn btn-sm btn-ghost" onclick="loadHistory(\'in\')">📥 Masuk</button>' +
        '<button class="btn btn-sm btn-ghost" onclick="loadHistory(\'out\')">📤 Keluar</button>' +
        '</div>' +
        '<div style="overflow:auto;flex:1;"><table style="width:100%"><thead><tr>' +
        '<th>Waktu</th><th>Arah</th><th>Nomor</th><th>Tipe</th><th>Pesan</th><th>Status</th>' +
        '</tr></thead><tbody id="hist-body"></tbody></table></div>' +
        '<div id="hist-pager" style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px"></div>' +
        '</div></div>';

    res.send(layout('Dashboard', 'Kelola nomor WhatsApp Anda', stats + table + modal + histModal + js + jsGroups, 'device'));
});

// ─── WEB API ──────────────────────────────────────────────────────────────────
app.get('/web/sessions', requireLogin, (req, res) => {
    const list = Array.from(sessions.entries()).map(([id, s]) => ({
        phone_id: id, label: s.label, status: s.status, groups: s.groupCache.size,
        api_token: s.apiToken || '', webhook_url: s.webhookUrl || '', webhook_enabled: s.webhookEnabled || false,
    }));
    res.json({ sessions: list });
});

app.post('/web/session/add', requireLogin, async (req, res) => {
    try {
        const phoneId = sanitizeId(req.body.phoneId || '');
        const label = String(req.body.label || '').trim().substring(0, 80) || phoneId;
        if (!phoneId) return res.status(400).json({ error: 'phoneId wajib diisi' });
        if (sessions.has(phoneId)) return res.status(409).json({ error: 'Sesi sudah ada' });
        const apiToken = randomBytes(16).toString('hex');
        await db.query('INSERT INTO wa_sessions(phone_id,label,api_token) VALUES($1,$2,$3) ON CONFLICT(phone_id) DO UPDATE SET label=$2', [phoneId, label, apiToken]);
        startSession(phoneId, label, apiToken, '', false);
        res.json({ success: true, phone_id: phoneId });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

app.post('/web/session/:id/delete', requireLogin, async (req, res) => {
    try {
        await removeSession(sanitizeId(req.params.id));
        res.json({ success: true });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

app.get('/web/session/:id/history', requireLogin, async (req, res) => {
    try {
        const id = sanitizeId(req.params.id);
        const page = Math.max(1, parseInt(req.query.page) || 1);
        const dir = req.query.dir === 'in' || req.query.dir === 'out' ? req.query.dir : null;
        const limit = 20; const offset = (page - 1) * limit;
        const conditions = ['session_id=$1'];
        const params = [id];
        if (dir) { params.push(dir); conditions.push('direction=$' + params.length); }
        const where = 'WHERE ' + conditions.join(' AND ');
        params.push(limit, offset);
        const { rows } = await db.query('SELECT direction,from_number,to_number,message,media_type,status,created_at FROM messages_log ' + where + ' ORDER BY created_at DESC LIMIT $' + (params.length - 1) + ' OFFSET $' + params.length, params).catch(() => ({ rows: [] }));
        const cParams = dir ? [id, dir] : [id];
        const cWhere = dir ? 'WHERE session_id=$1 AND direction=$2' : 'WHERE session_id=$1';
        const { rows: cnt } = await db.query('SELECT COUNT(*) as c FROM messages_log ' + cWhere, cParams).catch(() => ({ rows: [{ c: 0 }] }));
        res.json({ rows, total: parseInt(cnt[0]?.c || 0) });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

app.post('/web/session/:id/disconnect', requireLogin, async (req, res) => {
    try {
        const id = sanitizeId(req.params.id);
        const sess = sessions.get(id);
        if (!sess) return res.status(404).json({ error: 'Session not found' });
        if (sess._reconnectTimer) { clearTimeout(sess._reconnectTimer); sess._reconnectTimer = null; }
        if (sess.client) {
            const c = sess.client;
            sess.client = null;
            try { await Promise.race([c.logout(), new Promise(r => setTimeout(r, 10000))]); } catch { }
            try { await Promise.race([c.destroy(), new Promise(r => setTimeout(r, 5000))]); } catch { }
        }
        sess.status = 'disconnected';
        sess.qrCode = null;
        sess.qrDataUrl = null;
        // Hapus auth files agar bisa pairing ulang
        try { fs.rmSync(path.join('./auth_info', 'session-' + id), { recursive: true, force: true }); } catch { }
        try { fs.rmSync(getSessionAuthDir(id), { recursive: true, force: true }); } catch { }
        console.log('[WA][' + id + '] Disconnected by user');
        res.json({ success: true });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

app.post('/web/session/:id/webhook', requireLogin, async (req, res) => {
    try {
        const id = sanitizeId(req.params.id);
        const webhookUrl = String(req.body.webhook_url || '').trim().substring(0, 500);
        const webhookEnabled = req.body.webhook_enabled === true || req.body.webhook_enabled === 'true';
        const sess = sessions.get(id);
        if (!sess) return res.status(404).json({ ok: false, error: 'Session not found' });
        sess.webhookUrl = webhookUrl;
        sess.webhookEnabled = webhookEnabled;
        await db.query('UPDATE wa_sessions SET webhook_url=$1, webhook_enabled=$2 WHERE phone_id=$3', [webhookUrl, webhookEnabled, id]);
        res.json({ ok: true });
    } catch (e) { res.status(500).json({ ok: false, error: e.message }); }
});

app.post('/web/webhook/test', requireLogin, async (req, res) => {
    const url = String(req.body.url || '').trim();
    if (!url || !/^https?:\/\//i.test(url)) return res.status(400).json({ ok: false, error: 'URL tidak valid' });
    try {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone_id: 'test', message: 'Test webhook dari integrasi-wa', type: 'test', timestamp: Date.now() }),
            signal: AbortSignal.timeout(10000),
        });
        res.json({ ok: true, status: r.status });
    } catch (e) { res.json({ ok: false, error: e.message }); }
});

app.post('/web/session/:id/restart', requireLogin, async (req, res) => {
    const id = sanitizeId(req.params.id);
    if (!id) return res.json({ ok: false });
    const sess = sessions.get(id);
    if (!sess) return res.json({ ok: false, error: 'not found' });
    if (sess.status === 'open') return res.json({ ok: true, status: 'open' });
    if (sess.status === 'connecting' && sess.client) return res.json({ ok: true, status: 'connecting' });
    if (sess._reconnectTimer) { clearTimeout(sess._reconnectTimer); sess._reconnectTimer = null; }
    sess._failCount = 0;
    if (sess.client) { try { await sess.client.destroy(); } catch { } sess.client = null; }
    sess.status = 'disconnected';
    try { fs.rmSync(path.join('./auth_info', 'session-' + id), { recursive: true, force: true }); } catch { }
    try { fs.rmSync(getSessionAuthDir(id), { recursive: true, force: true }); } catch { }
    startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled);
    res.json({ ok: true, status: 'restarting' });
});

app.post('/web/session/:id/pairing-code', requireLogin, async (req, res) => {
    const id = sanitizeId(req.params.id);
    const phone = String(req.body.phone || '').replace(/[^0-9]/g, '');
    if (!id || !phone) return res.status(400).json({ error: 'id dan phone wajib diisi' });
    const existingSess = sessions.get(id);
    if (!existingSess) return res.status(404).json({ error: 'Session not found' });

    // Cooldown: prevent rapid pairing attempts (causes WhatsApp rate limit)
    const now = Date.now();
    const lastAttempt = existingSess._lastPairingAttempt || 0;
    const cooldownMs = 5 * 60 * 1000; // 5 minutes
    if (now - lastAttempt < cooldownMs) {
        const waitSec = Math.ceil((cooldownMs - (now - lastAttempt)) / 1000);
        return res.status(429).json({ error: 'Tunggu ' + waitSec + ' detik lagi sebelum mencoba pairing code. Ini untuk mencegah rate-limit WhatsApp.' });
    }
    existingSess._lastPairingAttempt = now;

    // Stop any current session
    if (existingSess._reconnectTimer) { clearTimeout(existingSess._reconnectTimer); existingSess._reconnectTimer = null; }
    if (existingSess.client) {
        const oldClient = existingSess.client;
        existingSess.client = null;
        try { await oldClient.destroy(); } catch { }
        // Wait for Chrome to fully exit before starting new instance
        await new Promise(r => setTimeout(r, 3000));
    }
    existingSess._failCount = 0;
    // Wipe auth for fresh pairing
    try { fs.rmSync(path.join('./auth_info', 'session-' + id), { recursive: true, force: true }); } catch { }
    try { fs.rmSync(getSessionAuthDir(id), { recursive: true, force: true }); } catch { }
    existingSess.status = 'connecting'; existingSess.qrCode = null; existingSess.qrDataUrl = null;

    try {
        const puppeteerArgs = ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--no-first-run', '--no-zygote'];
        const appSettings = await getAppSettings();
        // Use pairWithPhoneNumber so WA Web enters the correct ALT_DEVICE_LINKING
        // state from the start (instead of QR mode). The library calls
        // requestPairingCode without await, so we monkey-patch it to catch errors.
        const client = new Client({
            authStrategy: new LocalAuth({ clientId: id, dataPath: './auth_info' }),
            puppeteer: { headless: true, args: puppeteerArgs, ...(CHROME_PATH ? { executablePath: CHROME_PATH } : {}) },
            pairWithPhoneNumber: { phoneNumber: phone, showNotification: true },
            deviceName: appSettings.device_name || 'Integrasi-wa.jodyaryono.id',
            browserName: appSettings.browser_name || 'Google Chrome',
        });
        existingSess.client = client;

        const pairingCode = await new Promise((resolve, reject) => {
            const killTimer = setTimeout(() => reject(new Error('Timeout: WA tidak merespons dalam 120 detik.')), 120000);
            let done = false;
            const finish = (err, code) => {
                if (done) return;
                done = true; clearTimeout(killTimer);
                err ? reject(err) : resolve(code);
            };

            // Monkey-patch requestPairingCode: the library calls this without await,
            // and page.evaluate loses error details from minified WA Web code.
            // Our version wraps the browser-side code with try/catch to preserve error info,
            // waits for WA Web connection to be fully ready, and retries once on bad-request.
            client.requestPairingCode = async (phoneNumber, showNotification = true, intervalMs = 180000) => {
                console.log('[WA][' + id + '] requestPairingCode called, phone:', phoneNumber);
                const attemptPairing = () => client.pupPage.evaluate(async (pn, sn, ims) => {
                    try {
                        // Wait for WA Web connection to be fully established
                        let waited = 0;
                        while ((!window.AuthStore?.Conn?.ref || !window.AuthStore?.PairingCodeLinkUtils) && waited < 30000) {
                            await new Promise(r => setTimeout(r, 500));
                            waited += 500;
                        }
                        if (!window.AuthStore?.Conn?.ref) {
                            return { ok: false, name: 'ConnNotReady', msg: 'WA Web connection not established', errCode: null, errText: 'conn-not-ready', typeName: null };
                        }
                        const state = window.AuthStore?.AppState?.state;
                        if (state !== 'UNPAIRED' && state !== 'UNPAIRED_IDLE') {
                            return { ok: false, name: 'WrongState', msg: 'WA state: ' + state, errCode: null, errText: 'wrong-state', typeName: null };
                        }
                        console.log('[Pairing] Conn.ref ready, state:', state, '- starting alt linking flow');
                        const getCode = async () => {
                            window.AuthStore.PairingCodeLinkUtils.setPairingType('ALT_DEVICE_LINKING');
                            await window.AuthStore.PairingCodeLinkUtils.initializeAltDeviceLinking();
                            return window.AuthStore.PairingCodeLinkUtils.startAltLinkingFlow(pn, sn);
                        };
                        if (window.codeInterval) clearInterval(window.codeInterval);
                        window.codeInterval = setInterval(async () => {
                            if (window.AuthStore.AppState.state !== 'UNPAIRED' && window.AuthStore.AppState.state !== 'UNPAIRED_IDLE') {
                                clearInterval(window.codeInterval);
                                return;
                            }
                            try { window.onCodeReceivedEvent(await getCode()); } catch { }
                        }, ims);
                        const code = await getCode();
                        window.onCodeReceivedEvent(code);
                        return { ok: true, code };
                    } catch (e) {
                        return {
                            ok: false,
                            name: e?.name || 'Error',
                            msg: e?.message || String(e),
                            errCode: e?.type?.value?.code || e?.type?.code || null,
                            errText: e?.type?.value?.text || e?.type?.text || null,
                            typeName: e?.type?.value?.name || e?.type?.name || null,
                        };
                    }
                }, phoneNumber, showNotification, intervalMs);

                let result = await attemptPairing();
                // Retry once on bad-request (WA Web may need more time to stabilize)
                if (!result.ok && result.errText === 'bad-request') {
                    console.log('[WA][' + id + '] bad-request, retrying in 5s...');
                    await new Promise(r => setTimeout(r, 5000));
                    result = await attemptPairing();
                }

                if (result.ok) {
                    console.log('[WA][' + id + '] Pairing code from WA:', result.code);
                    return result.code;
                }
                // Build descriptive error
                console.error('[WA][' + id + '] Pairing rejected:', JSON.stringify(result));
                let msg = 'Gagal meminta kode pairing.';
                if (result.errCode === 429 || result.errText === 'rate-overlimit') {
                    msg = 'Rate limit WhatsApp. Tunggu 15-30 menit lalu coba lagi.';
                } else if (result.errText === 'bad-request') {
                    msg = 'WhatsApp menolak pairing (bad-request). Pastikan: 1) Perangkat tertaut di HP < 4, 2) Nomor aktif, 3) WhatsApp versi terbaru. Coba juga Scan QR sebagai alternatif.';
                } else if (result.errText === 'conn-not-ready') {
                    msg = 'Koneksi WA Web belum siap. Coba lagi dalam beberapa detik, atau gunakan Scan QR.';
                } else {
                    msg = 'Pairing ditolak: ' + (result.errText || result.typeName || result.name) + ' (' + (result.errCode || '?') + ')';
                }
                // Don't throw — library calls this without await, throwing causes unhandled rejection
                finish(new Error(msg));
            };

            client.on('code', (code) => {
                console.log('[WA][' + id + '] Pairing code received: ' + code);
                finish(null, code);
            });
            client.on('ready', () => {
                existingSess.status = 'open'; existingSess.qrCode = null; existingSess.qrDataUrl = null;
                existingSess._failCount = 0;
                console.log('[WA][' + id + '] Connected via pairing code');
                refreshGroupCacheForSession(id);
            });
            client.on('authenticated', () => { console.log('[WA][' + id + '] Authenticated via pairing'); });
            client.on('auth_failure', (msg) => {
                finish(new Error('Auth failure: ' + msg));
                existingSess.status = 'disconnected'; existingSess.client = null;
            });
            client.on('disconnected', (reason) => {
                console.log('[WA][' + id + '] Pairing connection closed (' + reason + ')');
                existingSess.status = 'disconnected'; existingSess.client = null;
                finish(new Error('Koneksi WA gagal (' + reason + ').'));
                const sessionDir = path.join('./auth_info', 'session-' + id);
                if (fs.existsSync(sessionDir)) {
                    existingSess._failCount = (existingSess._failCount || 0) + 1;
                    const delay = existingSess._failCount >= 3 ? 300000 : 20000;
                    existingSess._reconnectTimer = setTimeout(() => { existingSess._reconnectTimer = null; startSession(id, existingSess.label, existingSess.apiToken, existingSess.webhookUrl, existingSess.webhookEnabled); }, delay);
                }
            });
            client.on('message', async (msg) => {
                if (!msg.fromMe) await forwardToWebhook(msg, id, existingSess.groupCache);
            });

            client.initialize().catch(e => { finish(e); });
        });

        console.log('[WA][' + id + '] Pairing code issued: ' + pairingCode);
        res.json({ code: pairingCode });
    } catch (e) {
        console.error('[WA][' + id + '] Pairing code error:', e.message);
        existingSess.status = 'disconnected';
        // Properly cleanup the client so next attempt starts fresh
        const failedClient = existingSess.client;
        existingSess.client = null;
        if (failedClient) {
            try { await failedClient.destroy(); } catch { }
        }
        // Wipe any partial auth files left by the failed attempt
        try { fs.rmSync(path.join('./auth_info', 'session-' + id), { recursive: true, force: true }); } catch { }
        try { fs.rmSync(getSessionAuthDir(id), { recursive: true, force: true }); } catch { }
        if (!res.headersSent) res.status(503).json({ error: e.message });
    }
});

app.get('/web/qr/:id', requireLogin, (req, res) => {
    const id = sanitizeId(req.params.id);
    const s = sessions.get(id);
    if (!s) return res.status(404).json({ error: 'Session not found' });
    res.json({ status: s.status, label: s.label, qr: s.qrDataUrl || null });
});

// ─── QR PAGE ──────────────────────────────────────────────────────────────────
app.get('/qr/:id', requireLogin, (req, res) => {
    const phoneId = sanitizeId(req.params.id);
    const s = sessions.get(phoneId);
    if (!s) return res.redirect('/dashboard');

    if (s.status === 'open') {
        const body = '<div style="display:flex;justify-content:center;margin-top:48px;">' +
            '<div class="card" style="max-width:380px;width:100%;text-align:center;padding:38px 32px;">' +
            '<div style="font-size:3rem;margin-bottom:14px;">✅</div>' +
            '<div style="font-size:1.1rem;font-weight:700;margin-bottom:8px;">Sudah Terhubung</div>' +
            '<div style="color:#6b7280;margin-bottom:22px;">Nomor <strong>' + escHtml(s.label) + '</strong> aktif.</div>' +
            '<a href="/dashboard" class="btn btn-primary" style="justify-content:center;">← Dashboard</a>' +
            '</div></div>';
        return res.send(layout('Status QR', s.label, body, 'device'));
    }

    if (!s.qrDataUrl) {
        const body = '<div style="display:flex;justify-content:center;margin-top:48px;">' +
            '<div class="card" style="max-width:380px;width:100%;text-align:center;padding:38px 32px;">' +
            '<div style="font-size:3rem;margin-bottom:14px;">⏳</div>' +
            '<div style="font-size:1.1rem;font-weight:700;margin-bottom:8px;">Menunggu QR…</div>' +
            '<div style="color:#6b7280;margin-bottom:22px;">Harap tunggu, QR sedang dibuat.</div>' +
            '<a href="/dashboard" class="btn btn-ghost" style="margin-right:8px;">← Kembali</a>' +
            '<script>setTimeout(()=>location.reload(),3000)<' + '/script>' +
            '</div></div>';
        return res.send(layout('Menunggu QR', s.label, body, 'device'));
    }

    const body = '<div style="display:flex;justify-content:center;margin-top:40px;">' +
        '<div class="card" style="max-width:420px;width:100%;text-align:center;padding:36px 30px;">' +
        '<div style="font-size:1.15rem;font-weight:700;margin-bottom:6px;">📲 Scan QR Code</div>' +
        '<div style="color:#6b7280;font-size:.85rem;margin-bottom:18px;">Nomor: <strong>' + escHtml(s.label) + '</strong></div>' +
        '<img src="' + s.qrDataUrl + '" style="width:260px;height:260px;border-radius:12px;border:4px solid #e5e7eb;display:block;margin:0 auto 18px;" alt="QR Code">' +
        '<p style="color:#6b7280;font-size:.82rem;margin-bottom:20px;">Buka WhatsApp → Menu → Perangkat Tertaut → Tautkan Perangkat</p>' +
        '<div style="display:flex;gap:12px;justify-content:center;">' +
        '<a href="/dashboard" class="btn btn-ghost">← Kembali</a>' +
        '<button class="btn btn-primary" onclick="location.reload()">🔄 Refresh QR</button>' +
        '</div>' +
        '<script>setTimeout(()=>location.reload(),20000)<' + '/script>' +
        '</div></div>';
    res.send(layout('Scan QR — ' + phoneId, s.label, body, 'device'));
});

// ─── ROOT & MISC ──────────────────────────────────────────────────────────────
// ─── PAGE: SEND MESSAGE ───────────────────────────────────────────────────────
app.get('/send-message', requireLogin, (req, res) => {
    const sessList = Array.from(sessions.entries()).map(([id, s]) => ({ id, label: s.label, status: s.status }));
    const opts = sessList.map(s => '<option value="' + escHtml(s.id) + '"' + (s.status === 'open' ? '' : ' disabled') + '>' + escHtml(s.label) + ' (' + s.id + ')' + (s.status !== 'open' ? ' — Terputus' : '') + '</option>').join('');
    const body = '<div class="card" style="max-width:560px">' +
        '<div class="card-header"><span class="card-title">💬 Kirim Pesan</span></div>' +
        '<div class="card-body">' +
        '<form id="sf" autocomplete="off">' +
        '<div class="form-group"><label>Nomor WA (tujuan)</label>' +
        '<input class="input" id="s-num" placeholder="6281234567890 (tanpa + atau spasi)" required></div>' +
        '<div class="form-group"><label>Pesan</label>' +
        '<textarea class="input" id="s-msg" rows="5" placeholder="Tulis pesan…" required style="resize:vertical"></textarea></div>' +
        '<div class="form-group"><label>Kirim via Device</label>' +
        '<select class="input" id="s-pid">' + opts + '</select></div>' +
        '<button class="btn btn-primary" type="submit" id="s-btn"><span id="s-txt">📤 Kirim</span></button>' +
        '</form></div></div>' +
        '<script>' +
        'document.getElementById("sf").addEventListener("submit",async function(e){e.preventDefault();' +
        'const btn=document.getElementById("s-btn");const txt=document.getElementById("s-txt");' +
        'btn.disabled=true;txt.innerHTML=\'<span class="spin"></span> Mengirim…\';' +
        'try{const r=await fetch("/api/send",{method:"POST",headers:{"Content-Type":"application/json"},' +
        'body:JSON.stringify({phone_id:document.getElementById("s-pid").value,number:document.getElementById("s-num").value,message:document.getElementById("s-msg").value})});' +
        'const d=await r.json();' +
        'if(d.success){toast("✅ Pesan terkirim!");document.getElementById("s-msg").value="";}' +
        'else toast("❌ "+(d.error||"Gagal"),"err");}catch(e){toast("Error: "+e.message,"err");}' +
        'btn.disabled=false;txt.textContent="📤 Kirim";});</script>';
    res.send(layout('Send Message', 'Kirim pesan WhatsApp', body, 'send-message'));
});

// ─── PAGE: SEND TO GROUP ──────────────────────────────────────────────────────
app.get('/send-to-group', requireLogin, (req, res) => {
    const sessList = Array.from(sessions.entries()).map(([id, s]) => ({ id, label: s.label, status: s.status }));
    const opts = sessList.map(s => '<option value="' + escHtml(s.id) + '"' + (s.status === 'open' ? '' : ' disabled') + '>' + escHtml(s.label) + (s.status !== 'open' ? ' — Terputus' : '') + '</option>').join('');
    const body = '<div class="card" style="max-width:560px">' +
        '<div class="card-header"><span class="card-title">👥 Kirim ke Grup</span></div>' +
        '<div class="card-body">' +
        '<form id="gf" autocomplete="off">' +
        '<div class="form-group"><label>Device</label><select class="input" id="g-pid" onchange="loadGroups()">' + opts + '</select></div>' +
        '<div class="form-group"><label>Grup</label>' +
        '<select class="input" id="g-grp"><option value="">— Pilih grup —</option></select></div>' +
        '<div class="form-group"><label>Pesan</label>' +
        '<textarea class="input" id="g-msg" rows="5" placeholder="Tulis pesan…" required style="resize:vertical"></textarea></div>' +
        '<button class="btn btn-primary" type="submit" id="g-btn"><span id="g-txt">📤 Kirim ke Grup</span></button>' +
        '</form></div></div>' +
        '<script>' +
        'async function loadGroups(){const pid=document.getElementById("g-pid").value;if(!pid)return;' +
        'const sel=document.getElementById("g-grp");sel.innerHTML="<option>Memuat…</option>";' +
        'try{const r=await fetch("/api/groups?phone_id="+encodeURIComponent(pid));const d=await r.json();' +
        'sel.innerHTML="<option value=\'\'>\u2014 Pilih grup \u2014</option>"+(d.groups||[]).map(g=>"<option value=\'"+g.jid+"\'>" +g.name+" ("+g.participants+" anggota)</option>").join("");}' +
        'catch(e){sel.innerHTML="<option>Gagal memuat grup</option>";}}' +
        'loadGroups();' +
        'document.getElementById("gf").addEventListener("submit",async function(e){e.preventDefault();' +
        'const grp=document.getElementById("g-grp").value;if(!grp)return toast("Pilih grup dulu","err");' +
        'const btn=document.getElementById("g-btn");const txt=document.getElementById("g-txt");' +
        'btn.disabled=true;txt.innerHTML=\'<span class="spin"></span> Mengirim…\';' +
        'try{const r=await fetch("/api/sendGroup",{method:"POST",headers:{"Content-Type":"application/json"},' +
        'body:JSON.stringify({phone_id:document.getElementById("g-pid").value,group:grp,message:document.getElementById("g-msg").value})});' +
        'const d=await r.json();if(d.success){toast("✅ Terkirim ke grup!");document.getElementById("g-msg").value="";}' +
        'else toast("❌ "+(d.error||"Gagal"),"err");}catch(e){toast("Error: "+e.message,"err");}' +
        'btn.disabled=false;txt.textContent="📤 Kirim ke Grup";});</script>';
    res.send(layout('Send to Group', 'Kirim pesan ke grup WhatsApp', body, 'send-to-group'));
});

// ─── PAGE: SEND BROADCAST ─────────────────────────────────────────────────────
app.get('/send-broadcast', requireLogin, (req, res) => {
    const sessList = Array.from(sessions.entries()).map(([id, s]) => ({ id, label: s.label, status: s.status }));
    const opts = sessList.map(s => '<option value="' + escHtml(s.id) + '"' + (s.status === 'open' ? '' : ' disabled') + '>' + escHtml(s.label) + (s.status !== 'open' ? ' — Terputus' : '') + '</option>').join('');
    const body = '<div class="card" style="max-width:620px">' +
        '<div class="card-header"><span class="card-title">📢 Kirim Broadcast</span></div>' +
        '<div class="card-body">' +
        '<form id="bf" autocomplete="off">' +
        '<div class="form-group"><label>Device</label><select class="input" id="b-pid">' + opts + '</select></div>' +
        '<div class="form-group"><label>Nomor Tujuan <span class="hint">(satu per baris, format 628xxx)</span></label>' +
        '<textarea class="input" id="b-nums" rows="7" placeholder="6281234567890\n6289876543210\n..." style="resize:vertical;font-family:monospace;font-size:.85rem"></textarea></div>' +
        '<div class="form-group"><label>Pesan</label>' +
        '<textarea class="input" id="b-msg" rows="5" placeholder="Tulis pesan broadcast…" required style="resize:vertical"></textarea></div>' +
        '<div class="form-group" style="display:flex;align-items:center;gap:10px">' +
        '<label style="margin:0">Jeda per pesan (detik)</label>' +
        '<input class="input" id="b-delay" type="number" value="3" min="1" max="30" style="width:80px"></div>' +
        '<button class="btn btn-primary" type="submit" id="b-btn"><span id="b-txt">📢 Mulai Broadcast</span></button>' +
        '</form>' +
        '<div id="b-prog" style="display:none;margin-top:16px">' +
        '<div style="font-weight:600;margin-bottom:6px">Progress Broadcast</div>' +
        '<div id="b-bar-wrap" style="background:#e5e7eb;border-radius:6px;height:14px;overflow:hidden;margin-bottom:6px">' +
        '<div id="b-bar" style="height:100%;background:#10b981;width:0%;transition:width .3s"></div></div>' +
        '<div id="b-stat" style="font-size:.83rem;color:#6b7280"></div></div>' +
        '</div></div>' +
        '<script>' +
        'document.getElementById("bf").addEventListener("submit",async function(e){e.preventDefault();' +
        'const nums=document.getElementById("b-nums").value.split("\\n").map(x=>x.trim().replace(/\\D/g,"")).filter(x=>x.length>7);' +
        'if(!nums.length)return toast("Masukkan minimal 1 nomor","err");' +
        'const msg=document.getElementById("b-msg").value;if(!msg)return toast("Pesan kosong","err");' +
        'const pid=document.getElementById("b-pid").value;' +
        'const delay=parseInt(document.getElementById("b-delay").value)||3;' +
        'const btn=document.getElementById("b-btn");const txt=document.getElementById("b-txt");' +
        'btn.disabled=true;' +
        'const prog=document.getElementById("b-prog");prog.style.display="block";' +
        'const bar=document.getElementById("b-bar");const stat=document.getElementById("b-stat");' +
        'let sent=0,failed=0;' +
        'for(let i=0;i<nums.length;i++){' +
        '  txt.textContent="Mengirim "+(i+1)+"/"+nums.length+"…";' +
        '  try{const r=await fetch("/api/send",{method:"POST",headers:{"Content-Type":"application/json"},' +
        '    body:JSON.stringify({phone_id:pid,number:nums[i],message:msg})});' +
        '    const d=await r.json();if(d.success)sent++;else failed++;}catch{failed++;}' +
        '  bar.style.width=Math.round((i+1)/nums.length*100)+"%";' +
        '  stat.textContent="✅ "+sent+" terkirim  ❌ "+failed+" gagal dari "+(i+1)+"/"+nums.length;' +
        '  if(i<nums.length-1)await new Promise(r=>setTimeout(r,delay*1000));' +
        '}' +
        'txt.textContent="📢 Mulai Broadcast";btn.disabled=false;' +
        'toast("Broadcast selesai: "+sent+" terkirim, "+failed+" gagal");' +
        '// save to history\n' +
        'await fetch("/web/broadcast/save",{method:"POST",headers:{"Content-Type":"application/json"},' +
        '  body:JSON.stringify({session_id:pid,message:msg,recipients:nums,sent_count:sent,failed_count:failed})}).catch(()=>{});' +
        '});</script>';
    res.send(layout('Send Broadcast', 'Kirim pesan ke banyak nomor', body, 'send-broadcast'));
});

app.post('/web/broadcast/save', requireLogin, async (req, res) => {
    try {
        const { session_id, message, recipients, sent_count, failed_count } = req.body;
        await db.query('INSERT INTO broadcast_jobs(session_id,message,recipients,sent_count,failed_count,status) VALUES($1,$2,$3,$4,$5,$6)',
            [session_id || '', message || '', JSON.stringify(recipients || []), sent_count || 0, failed_count || 0, 'done']);
        res.json({ ok: true });
    } catch { res.json({ ok: false }); }
});

// ─── PAGE: BROADCAST HISTORY ──────────────────────────────────────────────────
app.get('/broadcast-history', requireLogin, async (req, res) => {
    const page = Math.max(1, parseInt(req.query.page) || 1);
    const limit = 20; const offset = (page - 1) * limit;
    const { rows } = await db.query('SELECT id,session_id,message,sent_count,failed_count,status,created_at,recipients FROM broadcast_jobs ORDER BY created_at DESC LIMIT $1 OFFSET $2', [limit, offset]).catch(() => ({ rows: [] }));
    const { rows: cnt } = await db.query('SELECT COUNT(*) as c FROM broadcast_jobs').catch(() => ({ rows: [{ c: 0 }] }));
    const total = parseInt(cnt[0]?.c || 0);
    const rows_html = rows.length ? rows.map(r => {
        const recips = (() => { try { return JSON.parse(r.recipients); } catch { return []; } })();
        return '<tr>' +
            '<td style="font-size:.8rem;color:#6b7280">' + new Date(r.created_at).toLocaleString('id-ID') + '</td>' +
            '<td>' + escHtml(r.session_id) + '</td>' +
            '<td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escHtml(r.message) + '</td>' +
            '<td>' + recips.length + ' nomor</td>' +
            '<td style="color:#10b981;font-weight:600">' + r.sent_count + '</td>' +
            '<td style="color:#ef4444;font-weight:600">' + r.failed_count + '</td>' +
            '</tr>';
    }).join('') : '<tr class="empty-row"><td colspan="6">Belum ada riwayat broadcast.</td></tr>';
    const pager = total > limit ? '<div style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end">' +
        (page > 1 ? '<a href="?page=' + (page - 1) + '" class="btn btn-ghost btn-sm">← Prev</a>' : '') +
        '<span style="line-height:32px;font-size:.85rem;color:#6b7280">Hal ' + page + ' / ' + Math.ceil(total / limit) + '</span>' +
        (page * limit < total ? '<a href="?page=' + (page + 1) + '" class="btn btn-ghost btn-sm">Next →</a>' : '') +
        '</div>' : '';
    const body = '<div class="card"><div class="card-header"><span class="card-title">📋 Riwayat Broadcast</span></div>' +
        '<div class="tbl-wrap"><table><thead><tr><th>Waktu</th><th>Device</th><th>Pesan</th><th>Total</th><th style="color:#10b981">Terkirim</th><th style="color:#ef4444">Gagal</th></tr></thead>' +
        '<tbody>' + rows_html + '</tbody></table></div>' + pager + '</div>';
    res.send(layout('Broadcast History', 'Riwayat pengiriman broadcast', body, 'broadcast-history'));
});

// ─── PAGE: MESSAGES HISTORY ───────────────────────────────────────────────────
app.get('/messages-history', requireLogin, async (req, res) => {
    const page = Math.max(1, parseInt(req.query.page) || 1);
    const dir = req.query.dir || '';
    const limit = 30; const offset = (page - 1) * limit;
    const where = dir ? 'WHERE direction=$3' : '';
    const params = dir ? [limit, offset, dir] : [limit, offset];
    const { rows } = await db.query('SELECT id,session_id,direction,from_number,to_number,message,media_type,status,created_at FROM messages_log ' + where + ' ORDER BY created_at DESC LIMIT $1 OFFSET $2', params).catch(() => ({ rows: [] }));
    const { rows: cnt } = await db.query('SELECT COUNT(*) as c FROM messages_log' + (dir ? ' WHERE direction=$1' : ''), (dir ? [dir] : [])).catch(() => ({ rows: [{ c: 0 }] }));
    const total = parseInt(cnt[0]?.c || 0);
    const filterBar = '<div style="display:flex;gap:8px;margin-bottom:14px">' +
        '<a href="?" class="btn btn-sm ' + (dir ? 'btn-ghost' : 'btn-primary') + '">Semua</a>' +
        '<a href="?dir=in" class="btn btn-sm ' + (dir === 'in' ? 'btn-primary' : 'btn-ghost') + '">📥 Masuk</a>' +
        '<a href="?dir=out" class="btn btn-sm ' + (dir === 'out' ? 'btn-primary' : 'btn-ghost') + '">📤 Keluar</a>' +
        '</div>';
    const rows_html = rows.length ? rows.map(r => {
        const dirBadge = r.direction === 'in' ? '<span class="badge badge-conn" style="font-size:.7rem">📥 Masuk</span>' : '<span class="badge badge-disc" style="font-size:.7rem">📤 Keluar</span>';
        return '<tr>' +
            '<td style="font-size:.78rem;color:#6b7280">' + new Date(r.created_at).toLocaleString('id-ID') + '</td>' +
            '<td>' + dirBadge + '</td>' +
            '<td style="font-family:monospace;font-size:.8rem">' + escHtml(r.direction === 'in' ? r.from_number : r.to_number) + '</td>' +
            '<td>' + escHtml(r.session_id) + '</td>' +
            '<td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escHtml(r.message) + '</td>' +
            '<td style="font-size:.78rem;color:#6b7280">' + escHtml(r.status) + '</td>' +
            '</tr>';
    }).join('') : '<tr class="empty-row"><td colspan="6">Belum ada riwayat pesan.</td></tr>';
    const pager = total > limit ? '<div style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end">' +
        (page > 1 ? '<a href="?dir=' + dir + '&page=' + (page - 1) + '" class="btn btn-ghost btn-sm">← Prev</a>' : '') +
        '<span style="line-height:32px;font-size:.85rem;color:#6b7280">Hal ' + page + ' / ' + Math.ceil(total / limit) + '</span>' +
        (page * limit < total ? '<a href="?dir=' + dir + '&page=' + (page + 1) + '" class="btn btn-ghost btn-sm">Next →</a>' : '') +
        '</div>' : '';
    const body = filterBar + '<div class="card"><div class="card-header"><span class="card-title">🗂️ Riwayat Pesan</span>' +
        '<span style="font-size:.82rem;color:#6b7280">' + total + ' total pesan</span></div>' +
        '<div class="tbl-wrap"><table><thead><tr><th>Waktu</th><th>Arah</th><th>Nomor</th><th>Device</th><th>Pesan</th><th>Status</th></tr></thead>' +
        '<tbody>' + rows_html + '</tbody></table></div>' + pager + '</div>';
    res.send(layout('Messages History', 'Riwayat semua pesan', body, 'messages-history'));
});

// ─── PAGE: CONTACT NUMBERS ────────────────────────────────────────────────────
app.get('/contacts', requireLogin, async (req, res) => {
    const { rows } = await db.query('SELECT id,name,phone,notes,created_at FROM contacts ORDER BY name').catch(() => ({ rows: [] }));
    const rows_html = rows.length ? rows.map(r =>
        '<tr>' +
        '<td><strong>' + escHtml(r.name) + '</strong></td>' +
        '<td style="font-family:monospace">' + escHtml(r.phone) + '</td>' +
        '<td>' + escHtml(r.notes) + '</td>' +
        '<td style="font-size:.78rem;color:#6b7280">' + new Date(r.created_at).toLocaleDateString('id-ID') + '</td>' +
        '<td><button class="btn btn-ghost btn-sm" onclick="editContact(' + r.id + ',\'' + escHtml(r.name) + '\',\'' + escHtml(r.phone) + '\',\'' + escHtml(r.notes) + '\')">✏️ Edit</button> ' +
        '<button class="btn btn-danger btn-sm" onclick="delContact(' + r.id + ')">🗑</button></td>' +
        '</tr>'
    ).join('') : '<tr class="empty-row"><td colspan="5">Belum ada kontak.</td></tr>';
    const body = '<div style="display:flex;justify-content:flex-end;margin-bottom:12px">' +
        '<button class="btn btn-primary" onclick="openCModal()">➕ Tambah Kontak</button></div>' +
        '<div class="card"><div class="card-header"><span class="card-title">📞 Daftar Kontak</span>' +
        '<span style="font-size:.82rem;color:#6b7280">' + rows.length + ' kontak</span></div>' +
        '<div class="tbl-wrap"><table><thead><tr><th>Nama</th><th>Nomor WA</th><th>Catatan</th><th>Ditambah</th><th>Aksi</th></tr></thead>' +
        '<tbody id="ctb">' + rows_html + '</tbody></table></div></div>' +
        '<div class="modal-overlay" id="c-modal"><div class="modal-box" style="max-width:420px;text-align:left">' +
        '<button class="modal-close" onclick="closeCModal()">✕</button>' +
        '<div style="font-size:1.1rem;font-weight:700;margin-bottom:18px" id="c-modal-title">➕ Tambah Kontak</div>' +
        '<form id="cf"><input type="hidden" id="c-id">' +
        '<div class="form-group"><label>Nama</label><input class="input" id="c-name" required></div>' +
        '<div class="form-group"><label>Nomor WA</label><input class="input" id="c-phone" placeholder="6281234567890" required></div>' +
        '<div class="form-group"><label>Catatan</label><input class="input" id="c-notes"></div>' +
        '<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px">' +
        '<button type="button" class="btn btn-ghost" onclick="closeCModal()">Batal</button>' +
        '<button class="btn btn-primary" type="submit" id="c-btn"><span id="c-txt">Simpan</span></button>' +
        '</div></form></div></div>' +
        '<script>' +
        'function openCModal(id,name,phone,notes){' +
        '  document.getElementById("c-id").value=id||"";' +
        '  document.getElementById("c-name").value=name||"";' +
        '  document.getElementById("c-phone").value=phone||"";' +
        '  document.getElementById("c-notes").value=notes||"";' +
        '  document.getElementById("c-modal-title").textContent=id?"✏️ Edit Kontak":"➕ Tambah Kontak";' +
        '  document.getElementById("c-modal").classList.add("open");' +
        '  setTimeout(()=>document.getElementById("c-name").focus(),80);}' +
        'function editContact(id,name,phone,notes){openCModal(id,name,phone,notes);}' +
        'function closeCModal(){document.getElementById("c-modal").classList.remove("open");document.getElementById("cf").reset();}' +
        'document.getElementById("cf").addEventListener("submit",async function(e){e.preventDefault();' +
        '  const id=document.getElementById("c-id").value;' +
        '  const data={name:document.getElementById("c-name").value,phone:document.getElementById("c-phone").value.replace(/\\D/g,""),notes:document.getElementById("c-notes").value};' +
        '  const btn=document.getElementById("c-btn");const txt=document.getElementById("c-txt");' +
        '  btn.disabled=true;txt.innerHTML=\'<span class="spin"></span>\';' +
        '  const url=id?"/web/contact/"+id+"/edit":"/web/contact/add";' +
        '  try{const r=await fetch(url,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)});' +
        '    const d=await r.json();if(d.ok){toast("Kontak disimpan");closeCModal();setTimeout(()=>location.reload(),600);}' +
        '    else toast(d.error||"Gagal","err");}catch(e){toast("Error: "+e.message,"err");}' +
        '  btn.disabled=false;txt.textContent="Simpan";});' +
        'async function delContact(id){if(!confirm("Hapus kontak ini?"))return;' +
        '  const r=await fetch("/web/contact/"+id+"/delete",{method:"POST"});const d=await r.json();' +
        '  if(d.ok){toast("Kontak dihapus");setTimeout(()=>location.reload(),600);}else toast(d.error||"Gagal","err");}' +
        'document.addEventListener("click",e=>{if(e.target&&e.target.id==="c-modal")closeCModal();});' +
        '</script>';
    res.send(layout('Contact Numbers', 'Kelola daftar kontak', body, 'contact-numbers'));
});

app.post('/web/contact/add', requireLogin, async (req, res) => {
    try {
        const { name, phone, notes } = req.body;
        const p = String(phone || '').replace(/\D/g, '');
        if (!name || !p) return res.json({ ok: false, error: 'Nama dan nomor wajib diisi' });
        await db.query('INSERT INTO contacts(name,phone,notes) VALUES($1,$2,$3)', [String(name).substring(0, 200), p.substring(0, 60), String(notes || '').substring(0, 500)]);
        res.json({ ok: true });
    } catch (e) { res.json({ ok: false, error: e.code === '23505' ? 'Nomor sudah ada' : e.message }); }
});
app.post('/web/contact/:id/edit', requireLogin, async (req, res) => {
    try {
        const { name, phone, notes } = req.body;
        const p = String(phone || '').replace(/\D/g, '');
        await db.query('UPDATE contacts SET name=$1,phone=$2,notes=$3 WHERE id=$4', [String(name).substring(0, 200), p.substring(0, 60), String(notes || '').substring(0, 500), parseInt(req.params.id)]);
        res.json({ ok: true });
    } catch (e) { res.json({ ok: false, error: e.message }); }
});
app.post('/web/contact/:id/delete', requireLogin, async (req, res) => {
    try { await db.query('DELETE FROM contacts WHERE id=$1', [parseInt(req.params.id)]); res.json({ ok: true }); }
    catch (e) { res.json({ ok: false, error: e.message }); }
});

// ─── PAGE: GROUPING NUMBERS ───────────────────────────────────────────────────
app.get('/groups', requireLogin, (req, res) => {
    const sessList = Array.from(sessions.entries()).filter(([, s]) => s.status === 'open');
    const opts = sessList.map(([id, s]) => '<option value="' + escHtml(id) + '">' + escHtml(s.label) + '</option>').join('') ||
        '<option value="">— Tidak ada device terhubung —</option>';
    const body = '<div class="card"><div class="card-header"><span class="card-title">👥 Daftar Grup WhatsApp</span>' +
        '<div style="display:flex;align-items:center;gap:10px">' +
        '<select class="input" id="g-sel" onchange="loadGrps()" style="width:200px">' + opts + '</select>' +
        '<button class="btn btn-ghost btn-sm" onclick="refreshGrps()">🔄 Refresh</button></div></div>' +
        '<div class="tbl-wrap"><table><thead><tr><th>Nama Grup</th><th>JID</th><th>Anggota</th><th>Aksi</th></tr></thead>' +
        '<tbody id="gtb"><tr class="empty-row"><td colspan="4">Pilih device untuk melihat grup.</td></tr></tbody>' +
        '</table></div></div>' +
        '<script>' +
        'async function loadGrps(){const pid=document.getElementById("g-sel").value;if(!pid)return;' +
        '  const tb=document.getElementById("gtb");tb.innerHTML=\'<tr><td colspan="4" style="text-align:center"><span class="spin" style="margin:8px auto;display:block"></span></td></tr>\';' +
        '  try{const r=await fetch("/api/groups?phone_id="+encodeURIComponent(pid));const d=await r.json();' +
        '    if(!d.groups?.length){tb.innerHTML=\'<tr class="empty-row"><td colspan="4">Tidak ada grup.</td></tr>\';return;}' +
        '    tb.innerHTML=d.groups.map(g=>"<tr><td><strong>"+g.name+"</strong></td><td style=\'font-size:.78rem;font-family:monospace;color:#6b7280\'>"+g.jid+"</td><td>"+g.participants+" anggota</td>' +
        '      <td><button class=\'btn btn-ghost btn-sm\' onclick=\'sendToGrp(\\\""+g.jid+"\\\",\\\""+g.name.replace(/\"/g,\'\\\\\"\')+"\\\")\'>💬 Kirim</button></td></tr>").join("");}' +
        '  catch(e){tb.innerHTML=\'<tr class="empty-row"><td colspan="4">Gagal memuat grup.</td></tr>\';}}' +
        'async function refreshGrps(){const pid=document.getElementById("g-sel").value;if(!pid)return;' +
        '  await fetch("/api/refresh-groups",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({phone_id:pid})});loadGrps();}' +
        'function sendToGrp(jid,name){location.href="/send-to-group?group="+encodeURIComponent(jid);}' +
        'loadGrps();</script>';
    res.send(layout('Grouping Numbers', 'Daftar grup WhatsApp', body, 'grouping-numbers'));
});

// ─── PAGE: WEBHOOK SETTINGS ───────────────────────────────────────────────────
app.get('/webhook-settings', requireLogin, (req, res) => {
    const body = '<div class="card" style="max-width:620px">' +
        '<div class="card-header"><span class="card-title">🔗 Webhook Settings</span></div>' +
        '<div class="card-body">' +
        '<div class="form-group"><label>Webhook URL</label>' +
        '<div style="display:flex;gap:8px">' +
        '<input class="input" id="wh-url" value="' + escHtml(WEBHOOK_URL || '') + '" placeholder="https://yourapp.com/webhook" style="flex:1"></div></div>' +
        '<div class="form-group" style="display:flex;align-items:center;gap:12px;margin-top:4px">' +
        '<label style="margin:0;display:flex;align-items:center;gap:8px;cursor:pointer">' +
        '<input type="checkbox" id="wh-en" style="width:18px;height:18px"' + (WEBHOOK_ENABLED ? ' checked' : '') + '> Aktifkan Webhook</label></div>' +
        '<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-top:16px;margin-bottom:16px">' +
        '<div style="font-weight:600;margin-bottom:8px;font-size:.9rem">Format Payload (POST JSON)</div>' +
        '<pre style="font-size:.75rem;color:#374151;overflow-x:auto;margin:0">{\n  "phone_id": "6281234567890",\n  "message": "Halo!",\n  "type": "text",\n  "sender": "628xxx",\n  "sender_name": "Budi",\n  "timestamp": 1234567890,\n  "group_id": null,\n  "_key": { "remoteJid": "...", "id": "...", "fromMe": false }\n}</pre>' +
        '</div>' +
        '<div style="display:flex;gap:10px">' +
        '<button class="btn btn-primary" onclick="saveWebhook()">💾 Simpan ke .env</button>' +
        '<button class="btn btn-ghost" onclick="testWebhook()">🧪 Test Webhook</button>' +
        '</div>' +
        '<div id="wh-result" style="margin-top:12px;font-size:.85rem"></div>' +
        '</div></div>' +
        '<script>' +
        'function saveWebhook(){toast("Info: Ubah WEBHOOK_URL dan WEBHOOK_ENABLED di file .env di VPS, lalu restart server.","err");}' +
        'async function testWebhook(){' +
        '  const url=document.getElementById("wh-url").value;if(!url)return toast("Masukkan URL webhook dulu","err");' +
        '  const res=document.getElementById("wh-result");res.innerHTML=\'<span class="spin" style="display:inline-block;margin-right:6px"></span>Mengirim test...\';' +
        '  try{const r=await fetch("/web/webhook/test",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({url})});' +
        '    const d=await r.json();' +
        '    res.innerHTML=d.ok?\'<span style="color:#10b981">✅ Berhasil — HTTP \'+d.status+\'</span>\':\'<span style="color:#ef4444">❌ Gagal: \'+d.error+\'</span>\';}' +
        '  catch(e){res.innerHTML=\'<span style="color:#ef4444">❌ Error: \'+e.message+\'</span>\';}' +
        '}</script>';
    res.send(layout('Webhook', 'Konfigurasi webhook', body, 'webhook'));
});

app.post('/web/webhook/test', requireLogin, async (req, res) => {
    const url = String(req.body.url || '');
    if (!url.startsWith('http')) return res.json({ ok: false, error: 'URL tidak valid' });
    try {
        const r = await fetch(url, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone_id: 'test', message: 'Test webhook dari Integrasi WA', type: 'text', sender: '628000', timestamp: Math.floor(Date.now() / 1000), _test: true }),
        });
        res.json({ ok: true, status: r.status });
    } catch (e) { res.json({ ok: false, error: e.message }); }
});

// ─── PAGE: AUTOREPLY ──────────────────────────────────────────────────────────
app.get('/autoreply', requireLogin, async (req, res) => {
    const { rows } = await db.query('SELECT id,keyword,reply,is_regex,enabled,created_at FROM autoreply_rules ORDER BY id').catch(() => ({ rows: [] }));
    const rows_html = rows.length ? rows.map(r =>
        '<tr>' +
        '<td><code style="font-size:.82rem;background:#f3f4f6;padding:2px 6px;border-radius:4px">' + escHtml(r.keyword) + '</code>' +
        (r.is_regex ? '<span style="font-size:.7rem;background:#fef3c7;color:#92400e;padding:1px 5px;border-radius:3px;margin-left:5px">regex</span>' : '') + '</td>' +
        '<td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escHtml(r.reply) + '</td>' +
        '<td><span class="badge ' + (r.enabled ? 'badge-open' : 'badge-disc') + '" style="font-size:.7rem">' + (r.enabled ? '✅ Aktif' : '⛔ Mati') + '</span></td>' +
        '<td style="display:flex;gap:6px">' +
        '<button class="btn btn-ghost btn-sm" onclick="editAR(' + r.id + ',`' + escHtml(r.keyword).replace(/`/g, '\\`') + '`,`' + escHtml(r.reply).replace(/`/g, '\\`') + '`,' + r.is_regex + ',' + r.enabled + ')">✏️</button>' +
        '<button class="btn btn-danger btn-sm" onclick="delAR(' + r.id + ')">🗑</button></td>' +
        '</tr>'
    ).join('') : '<tr class="empty-row"><td colspan="4">Belum ada aturan autoreply.</td></tr>';
    const body = '<div style="display:flex;justify-content:flex-end;margin-bottom:12px">' +
        '<button class="btn btn-primary" onclick="openARModal()">➕ Tambah Aturan</button></div>' +
        '<div class="card"><div class="card-header"><span class="card-title">🔄 Autoreply Rules</span>' +
        '<span style="font-size:.82rem;color:#6b7280">' + rows.length + ' aturan</span></div>' +
        '<div class="tbl-wrap"><table><thead><tr><th>Keyword / Trigger</th><th>Balasan</th><th>Status</th><th>Aksi</th></tr></thead>' +
        '<tbody>' + rows_html + '</tbody></table></div></div>' +
        '<div class="modal-overlay" id="ar-modal"><div class="modal-box" style="max-width:460px;text-align:left">' +
        '<button class="modal-close" onclick="closeARModal()">✕</button>' +
        '<div style="font-size:1.1rem;font-weight:700;margin-bottom:18px" id="ar-title">➕ Tambah Aturan</div>' +
        '<form id="arf"><input type="hidden" id="ar-id">' +
        '<div class="form-group"><label>Keyword <span class="hint">(pesan yang mengandung kata ini akan dibalas)</span></label>' +
        '<input class="input" id="ar-kw" required placeholder="halo"></div>' +
        '<div class="form-group"><label>Balasan Otomatis</label>' +
        '<textarea class="input" id="ar-reply" rows="4" required style="resize:vertical"></textarea></div>' +
        '<div class="form-group" style="display:flex;gap:20px">' +
        '<label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" id="ar-regex"> Keyword adalah Regex</label>' +
        '<label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" id="ar-en" checked> Aktif</label>' +
        '</div>' +
        '<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px">' +
        '<button type="button" class="btn btn-ghost" onclick="closeARModal()">Batal</button>' +
        '<button class="btn btn-primary" type="submit"><span id="ar-btn-txt">Simpan</span></button>' +
        '</div></form></div></div>' +
        '<script>' +
        'function openARModal(id,kw,reply,regex,enabled){' +
        '  document.getElementById("ar-id").value=id||"";' +
        '  document.getElementById("ar-kw").value=kw||"";' +
        '  document.getElementById("ar-reply").value=reply||"";' +
        '  document.getElementById("ar-regex").checked=!!regex;' +
        '  document.getElementById("ar-en").checked=enabled!==false;' +
        '  document.getElementById("ar-title").textContent=id?"✏️ Edit Aturan":"➕ Tambah Aturan";' +
        '  document.getElementById("ar-modal").classList.add("open");' +
        '  setTimeout(()=>document.getElementById("ar-kw").focus(),80);}' +
        'function editAR(id,kw,reply,regex,enabled){openARModal(id,kw,reply,regex,enabled);}' +
        'function closeARModal(){document.getElementById("ar-modal").classList.remove("open");document.getElementById("arf").reset();}' +
        'document.getElementById("arf").addEventListener("submit",async function(e){e.preventDefault();' +
        '  const id=document.getElementById("ar-id").value;' +
        '  const data={keyword:document.getElementById("ar-kw").value,reply:document.getElementById("ar-reply").value,' +
        '    is_regex:document.getElementById("ar-regex").checked,enabled:document.getElementById("ar-en").checked};' +
        '  const url=id?"/web/autoreply/"+id+"/edit":"/web/autoreply/add";' +
        '  const txt=document.getElementById("ar-btn-txt");txt.innerHTML=\'<span class="spin"></span>\';' +
        '  try{const r=await fetch(url,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)});' +
        '    const d=await r.json();if(d.ok){toast("Aturan disimpan");closeARModal();setTimeout(()=>location.reload(),600);}' +
        '    else toast(d.error||"Gagal","err");}catch(e){toast("Error: "+e.message,"err");}' +
        '  txt.textContent="Simpan";});' +
        'async function delAR(id){if(!confirm("Hapus aturan ini?"))return;' +
        '  const r=await fetch("/web/autoreply/"+id+"/delete",{method:"POST"});const d=await r.json();' +
        '  if(d.ok){toast("Aturan dihapus");setTimeout(()=>location.reload(),600);}else toast(d.error||"Gagal","err");}' +
        'document.addEventListener("click",e=>{if(e.target&&e.target.id==="ar-modal")closeARModal();});' +
        '</script>';
    res.send(layout('Autoreply', 'Aturan balas otomatis', body, 'autoreply'));
});

app.post('/web/autoreply/add', requireLogin, async (req, res) => {
    try {
        const { keyword, reply, is_regex, enabled } = req.body;
        if (!keyword || !reply) return res.json({ ok: false, error: 'Keyword dan reply wajib diisi' });
        await db.query('INSERT INTO autoreply_rules(keyword,reply,is_regex,enabled) VALUES($1,$2,$3,$4)',
            [String(keyword).substring(0, 500), String(reply), !!is_regex, enabled !== false]);
        res.json({ ok: true });
    } catch (e) { res.json({ ok: false, error: e.message }); }
});
app.post('/web/autoreply/:id/edit', requireLogin, async (req, res) => {
    try {
        const { keyword, reply, is_regex, enabled } = req.body;
        await db.query('UPDATE autoreply_rules SET keyword=$1,reply=$2,is_regex=$3,enabled=$4 WHERE id=$5',
            [String(keyword).substring(0, 500), String(reply), !!is_regex, enabled !== false, parseInt(req.params.id)]);
        res.json({ ok: true });
    } catch (e) { res.json({ ok: false, error: e.message }); }
});
app.post('/web/autoreply/:id/delete', requireLogin, async (req, res) => {
    try { await db.query('DELETE FROM autoreply_rules WHERE id=$1', [parseInt(req.params.id)]); res.json({ ok: true }); }
    catch (e) { res.json({ ok: false, error: e.message }); }
});

// ─── PAGE: API MANUAL ─────────────────────────────────────────────────────────
app.get('/api-manual', requireLogin, (req, res) => {
    const baseUrl = req.protocol + '://' + req.get('host');
    const token = AUTH_TOKEN || 'YOUR_AUTH_TOKEN';
    const hdr = 'Authorization: Bearer ' + token;

    function apiCard(title, desc, method, endpoint, bodyFields, exampleBody, exampleResp, notes) {
        const mColor = method === 'GET' ? '#0ea5e9' : '#f59e0b';
        const fields = bodyFields.map(f => '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46;white-space:nowrap">' + f.name + (f.required ? ' <span style="color:#ef4444">*</span>' : '') + '</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">' + f.type + '</td><td style="padding:6px 10px;font-size:.82rem">' + f.desc + '</td></tr>').join('');
        const cardId = 'api-' + endpoint.replace(/[^a-zA-Z0-9]/g, '_');
        const fullUrl = baseUrl + endpoint;

        // Generate cURL example
        const curlEx = method === 'GET'
            ? 'curl -X GET "' + fullUrl + (exampleBody ? '?' + Object.entries(exampleBody).map(([k,v]) => k + '=' + v).join('&') : '') + '" \\\n  -H "' + hdr + '"'
            : 'curl -X POST "' + fullUrl + '" \\\n  -H "' + hdr + '" \\\n  -H "Content-Type: application/json" \\\n  -d \'' + (exampleBody ? JSON.stringify(exampleBody) : '{}') + '\'';

        // Generate PHP CodeIgniter example
        let phpEx = '';
        if (method === 'GET') {
            const qp = exampleBody ? '?' + Object.entries(exampleBody).map(([k,v]) => k + '=' + v).join('&') : '';
            phpEx = '$client = \\Config\\Services::curlrequest();\n\n$response = $client->get(\'' + fullUrl + qp + '\', [\n    \'headers\' => [\n        \'Authorization\' => \'Bearer ' + token + '\'\n    ]\n]);\n\n$data = json_decode($response->getBody(), true);';
        } else {
            phpEx = '$client = \\Config\\Services::curlrequest();\n\n$response = $client->post(\'' + fullUrl + '\', [\n    \'headers\' => [\n        \'Authorization\' => \'Bearer ' + token + '\',\n        \'Content-Type\'  => \'application/json\'\n    ],\n    \'json\' => ' + (exampleBody ? JSON.stringify(exampleBody, null, 4).replace(/"/g, "'").replace(/\n/g, '\n    ') : '[]') + '\n]);\n\n$data = json_decode($response->getBody(), true);';
        }

        // Generate JavaScript (fetch) example
        let jsEx = '';
        if (method === 'GET') {
            const qp = exampleBody ? '?' + Object.entries(exampleBody).map(([k,v]) => k + '=' + encodeURIComponent(v)).join('&') : '';
            jsEx = 'const res = await fetch(\'' + fullUrl + qp + '\', {\n  headers: { \'Authorization\': \'Bearer ' + token + '\' }\n});\nconst data = await res.json();\nconsole.log(data);';
        } else {
            jsEx = 'const res = await fetch(\'' + fullUrl + '\', {\n  method: \'POST\',\n  headers: {\n    \'Authorization\': \'Bearer ' + token + '\',\n    \'Content-Type\': \'application/json\'\n  },\n  body: JSON.stringify(' + (exampleBody ? JSON.stringify(exampleBody, null, 2).replace(/\n/g, '\n  ') : '{}') + ')\n});\nconst data = await res.json();\nconsole.log(data);';
        }

        const tabBtns = '<div style="display:flex;gap:0;margin-bottom:0;border-bottom:2px solid #e5e7eb">' +
            '<button class="api-tab-btn active" onclick="switchTab(this,\'' + cardId + '\',\'curl\')" style="padding:6px 14px;font-size:.75rem;font-weight:600;border:none;background:none;cursor:pointer;border-bottom:2px solid #10b981;margin-bottom:-2px;color:#065f46">cURL</button>' +
            '<button class="api-tab-btn" onclick="switchTab(this,\'' + cardId + '\',\'php\')" style="padding:6px 14px;font-size:.75rem;font-weight:600;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;color:#6b7280">PHP CodeIgniter</button>' +
            '<button class="api-tab-btn" onclick="switchTab(this,\'' + cardId + '\',\'js\')" style="padding:6px 14px;font-size:.75rem;font-weight:600;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;color:#6b7280">JavaScript</button>' +
            '</div>';
        const tabPanels = '<div id="' + cardId + '-curl" class="api-tab-panel" style="display:block">' +
            '<div style="background:#1e293b;color:#e2e8f0;border-radius:0 0 8px 8px;padding:12px 16px;font-family:monospace;font-size:.78rem;overflow-x:auto;white-space:pre-wrap;word-break:break-all">' + escHtml(curlEx) + '</div></div>' +
            '<div id="' + cardId + '-php" class="api-tab-panel" style="display:none">' +
            '<div style="background:#1e293b;color:#e2e8f0;border-radius:0 0 8px 8px;padding:12px 16px;font-family:monospace;font-size:.78rem;overflow-x:auto;white-space:pre-wrap;word-break:break-all">' + escHtml(phpEx) + '</div></div>' +
            '<div id="' + cardId + '-js" class="api-tab-panel" style="display:none">' +
            '<div style="background:#1e293b;color:#e2e8f0;border-radius:0 0 8px 8px;padding:12px 16px;font-family:monospace;font-size:.78rem;overflow-x:auto;white-space:pre-wrap;word-break:break-all">' + escHtml(jsEx) + '</div></div>';

        return '<div class="card" style="margin-bottom:18px">' +
            '<div class="card-header" style="display:flex;align-items:center;gap:10px">' +
            '<span style="background:' + mColor + ';color:#fff;font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:4px;font-family:monospace">' + method + '</span>' +
            '<span class="card-title" style="font-size:.95rem">' + title + '</span></div>' +
            '<div class="card-body">' +
            '<p style="color:#6b7280;font-size:.85rem;margin-bottom:12px">' + desc + '</p>' +
            '<div style="margin-bottom:12px">' + tabBtns + tabPanels + '</div>' +
            (bodyFields.length ? '<div style="margin-bottom:12px"><div style="font-weight:600;font-size:.85rem;margin-bottom:6px">Parameter</div>' +
                '<table style="width:100%;border-collapse:collapse;font-size:.85rem"><thead><tr style="background:#f8fafc;text-align:left"><th style="padding:8px 10px;font-weight:600">Nama</th><th style="padding:8px 10px;font-weight:600">Tipe</th><th style="padding:8px 10px;font-weight:600">Keterangan</th></tr></thead><tbody>' + fields + '</tbody></table></div>' : '') +
            '<div style="margin-bottom:8px"><div style="font-weight:600;font-size:.85rem;margin-bottom:6px">Response</div>' +
            '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;font-family:monospace;font-size:.78rem;white-space:pre-wrap;word-break:break-all">' + escHtml(JSON.stringify(exampleResp, null, 2)) + '</div></div>' +
            (notes ? '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;margin-top:8px;font-size:.8rem;color:#92400e">' + notes + '</div>' : '') +
            '</div></div>';
    }

    const body = '<div style="max-width:800px">' +
        '<div class="card" style="margin-bottom:20px;background:linear-gradient(135deg,#064e3b,#065f46);color:#fff">' +
        '<div class="card-body" style="padding:24px 28px">' +
        '<div style="font-size:1.3rem;font-weight:700;margin-bottom:6px">📖 API Manual</div>' +
        '<div style="font-size:.9rem;color:#a7f3d0;margin-bottom:14px">Dokumentasi lengkap REST API WhatsApp Gateway</div>' +
        '<div style="background:rgba(0,0,0,.25);border-radius:8px;padding:12px 16px;font-family:monospace;font-size:.8rem;color:#ecfdf5">Base URL: ' + escHtml(baseUrl) + '\nAuth: Bearer ' + escHtml(token) + '</div>' +
        '</div></div>' +

        '<div style="font-size:1.1rem;font-weight:700;margin:24px 0 14px;padding-bottom:8px;border-bottom:2px solid #10b981;color:#064e3b">📊 Status & Informasi</div>' +

        apiCard('Status Semua Session', 'Melihat status semua session yang aktif.', 'GET', '/api/status', [], null,
            { sessions: { "628xxx": { label: "WA AI", status: "open", groups_cached: 5 } }, uptime: 3600 }, null) +

        apiCard('QR Code Session', 'Mendapatkan QR code untuk scan ulang session.', 'GET', '/api/qr/:id',
            [{ name: ':id', type: 'path', desc: 'Phone ID session (contoh: 6281234567890)', required: true }], null,
            { status: "connecting", qr: "data:image/png;base64,..." },
            'Status bisa: open, connecting, waiting') +

        '<div style="font-size:1.1rem;font-weight:700;margin:24px 0 14px;padding-bottom:8px;border-bottom:2px solid #0ea5e9;color:#0c4a6e">👤 Personal (Per Nomor)</div>' +

        apiCard('Kirim Pesan Teks', 'Mengirim pesan teks ke nomor personal.', 'POST', '/api/send',
            [
                { name: 'number', type: 'string', desc: 'Nomor tujuan (628xxx)', required: true },
                { name: 'message', type: 'string', desc: 'Isi pesan yang akan dikirim', required: true },
                { name: 'phone_id', type: 'string', desc: 'Phone ID pengirim (opsional, default session pertama)', required: false },
            ],
            { phone_id: "6281317647379", number: "6281234567890", message: "Halo, apa kabar?" },
            { success: true, phone_id: "6281317647379", data: { id: "3EB0xxxx" } }, null) +

        apiCard('Kirim Gambar', 'Mengirim gambar dengan caption opsional ke nomor personal.', 'POST', '/api/send-image',
            [
                { name: 'number', type: 'string', desc: 'Nomor tujuan (628xxx)', required: true },
                { name: 'image', type: 'string', desc: 'URL gambar (https://...)', required: true },
                { name: 'message', type: 'string', desc: 'Caption gambar (opsional)', required: false },
                { name: 'phone_id', type: 'string', desc: 'Phone ID pengirim (opsional)', required: false },
            ],
            { phone_id: "6281317647379", number: "6281234567890", image: "https://example.com/foto.jpg", message: "Lihat ini!" },
            { success: true, phone_id: "6281317647379", data: { id: "3EB0xxxx" } }, null) +

        '<div style="font-size:1.1rem;font-weight:700;margin:24px 0 14px;padding-bottom:8px;border-bottom:2px solid #f59e0b;color:#78350f">👥 Grup</div>' +

        apiCard('Daftar Grup', 'Mendapatkan daftar semua grup WhatsApp yang ter-cache.', 'GET', '/api/groups?phone_id=:id',
            [{ name: 'phone_id', type: 'query', desc: 'Phone ID session (opsional)', required: false }], null,
            { phone_id: "6281317647379", groups: [{ jid: "120363xxx@g.us", name: "Grup Kerja", participants: 25 }] }, null) +

        apiCard('Refresh Grup', 'Memuat ulang cache daftar grup dari WhatsApp.', 'POST', '/api/refresh-groups',
            [{ name: 'phone_id', type: 'string', desc: 'Phone ID session (opsional)', required: false }],
            { phone_id: "6281317647379" },
            { success: true, phone_id: "6281317647379", groups: [{ jid: "120363xxx@g.us", name: "Grup Kerja", participants: 25 }] }, null) +

        apiCard('Daftar Anggota Grup', 'Mengambil semua nomor HP anggota grup beserta role (admin/member). Menggunakan data live jika session aktif, fallback ke cache.', 'GET', '/api/group-members',
            [
                { name: 'group_id', type: 'query', desc: 'JID grup (xxx@g.us)', required: true },
                { name: 'phone_id', type: 'query', desc: 'Phone ID session (opsional)', required: false },
            ], null,
            { phone_id: "6281317647379", group_id: "120363xxx@g.us", total: 3, members: [{ number: "6281234567890", role: "superadmin" }, { number: "6289876543210", role: "admin" }, { number: "6281111222333", role: "member" }] }, null) +

        apiCard('Kirim Pesan ke Grup', 'Mengirim pesan teks ke grup WhatsApp.', 'POST', '/api/sendGroup',
            [
                { name: 'group', type: 'string', desc: 'JID grup (xxx@g.us) atau nama grup', required: true },
                { name: 'message', type: 'string', desc: 'Isi pesan yang akan dikirim', required: true },
                { name: 'phone_id', type: 'string', desc: 'Phone ID pengirim (opsional)', required: false },
            ],
            { phone_id: "6281317647379", group: "Grup Kerja", message: "Pengumuman penting!" },
            { success: true, phone_id: "6281317647379", data: { id: "3EB0xxxx", group_jid: "120363xxx@g.us" } },
            'Bisa pakai nama grup (partial match) atau JID lengkap (xxx@g.us)') +

        apiCard('Hapus Pesan di Grup', 'Menghapus pesan di grup (untuk semua anggota). Pesan harus masih dalam 50 pesan terakhir.', 'POST', '/api/delete',
            [
                { name: 'group_id', type: 'string', desc: 'JID atau nama grup', required: true },
                { name: 'message_id', type: 'string', desc: 'ID pesan yang akan dihapus', required: true },
                { name: 'participant', type: 'string', desc: 'Nomor pengirim pesan (opsional, untuk pesan orang lain)', required: false },
                { name: 'phone_id', type: 'string', desc: 'Phone ID (opsional)', required: false },
            ],
            { phone_id: "6281317647379", group_id: "120363xxx@g.us", message_id: "3EB0abc123", participant: "6281234567890" },
            { success: true, deleted: "3EB0abc123" },
            'Alternatif: kirim field <code>key</code> atau <code>_key</code> langsung berisi objek {remoteJid, id, fromMe, participant}') +

        apiCard('Kick Anggota Grup', 'Mengeluarkan anggota dari grup. Bot harus jadi admin grup.', 'POST', '/api/kick',
            [
                { name: 'group_id', type: 'string', desc: 'JID atau nama grup', required: true },
                { name: 'member', type: 'string', desc: 'Nomor anggota yang akan di-kick (628xxx)', required: true },
                { name: 'phone_id', type: 'string', desc: 'Phone ID (opsional)', required: false },
            ],
            { phone_id: "6281317647379", group_id: "Grup Kerja", member: "6281234567890" },
            { success: true, data: {} },
            '⚠️ Bot harus menjadi admin di grup tersebut untuk bisa kick anggota.') +

        '</div>' +
        '<script>' +
        'function switchTab(btn,cardId,lang){' +
        '  const card=btn.closest(".card");' +
        '  card.querySelectorAll(".api-tab-btn").forEach(b=>{b.style.borderBottomColor="transparent";b.style.color="#6b7280";b.classList.remove("active");});' +
        '  btn.style.borderBottomColor="#10b981";btn.style.color="#065f46";btn.classList.add("active");' +
        '  card.querySelectorAll(".api-tab-panel").forEach(p=>p.style.display="none");' +
        '  document.getElementById(cardId+"-"+lang).style.display="block";' +
        '}' +
        '</script>';

    res.send(layout('API Manual', 'Dokumentasi API WhatsApp Gateway', body, 'api-manual'));
});

// ─── PAGE: GENERAL SETTINGS ───────────────────────────────────────────────────
app.get('/settings', requireLogin, async (req, res) => {
    const settings = await getAppSettings();
    const dn = escHtml(settings.device_name || 'Integrasi-wa.jodyaryono.id');
    const bn = escHtml(settings.browser_name || 'Google Chrome');
    const body =
        '<div class="card" style="max-width:600px">' +
        '<div class="card-header"><span class="card-title">⚙️ General Settings</span></div>' +
        '<div class="card-body">' +
        '<form id="settings-form">' +
        '<div class="form-group"><label>Device Name</label>' +
        '<input class="input" type="text" name="device_name" value="' + dn + '" placeholder="Integrasi-wa.jodyaryono.id" required>' +
        '<small style="color:#6b7280;font-size:.78rem;">Nama yang muncul di perangkat tertaut WhatsApp.</small></div>' +
        '<div class="form-group"><label>Browser Name</label>' +
        '<input class="input" type="text" name="browser_name" value="' + bn + '" placeholder="Google Chrome" required>' +
        '<small style="color:#6b7280;font-size:.78rem;">Nama browser yang ditampilkan di WhatsApp.</small></div>' +
        '<button class="btn btn-primary" type="submit" style="margin-top:8px;">💾 Simpan</button>' +
        '</form>' +
        '<p style="margin-top:14px;font-size:.8rem;color:#9ca3af;">⚠️ Perubahan akan berlaku saat sesi baru dibuat atau pairing ulang.</p>' +
        '</div></div>' +
        '<script>' +
        'document.getElementById("settings-form").addEventListener("submit",async function(e){' +
        '  e.preventDefault();' +
        '  const fd=new FormData(this);' +
        '  const body={device_name:fd.get("device_name"),browser_name:fd.get("browser_name")};' +
        '  const r=await fetch("/settings",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(body)});' +
        '  const d=await r.json();' +
        '  if(d.success) toast("Settings tersimpan!");else toast(d.error||"Gagal menyimpan","err");' +
        '});' +
        '</script>';
    res.send(layout('General Settings', 'Setting > General Settings', body, 'general-settings'));
});
app.post('/settings', requireLogin, async (req, res) => {
    try {
        const { device_name, browser_name } = req.body;
        if (!device_name || !browser_name) return res.status(400).json({ error: 'Semua field wajib diisi' });
        await saveAppSetting('device_name', String(device_name).substring(0, 100));
        await saveAppSetting('browser_name', String(browser_name).substring(0, 100));
        res.json({ success: true });
    } catch (e) {
        console.error('[Settings] Save error:', e.message);
        res.status(500).json({ error: 'Gagal menyimpan settings' });
    }
});

// ─── ROOT & MISC ──────────────────────────────────────────────────────────────
app.get('/', (req, res) => { if (req.session?.loggedIn) return res.redirect('/dashboard'); return res.redirect('/login'); });
app.get('/webhook', (req, res) => res.json({ webhook_url: WEBHOOK_URL || 'not configured', webhook_enabled: WEBHOOK_ENABLED }));

// ─── PUBLIC API ───────────────────────────────────────────────────────────────
app.get('/api/status', apiAuth, (req, res) => {
    const data = {};
    for (const [id, s] of sessions) data[id] = { label: s.label, status: s.status, groups_cached: s.groupCache.size };
    res.json({ sessions: data, uptime: process.uptime() });
});
app.get('/api/qr/:id', apiAuth, (req, res) => {
    const id = sanitizeId(req.params.id); const s = sessions.get(id);
    if (!s) return res.status(404).json({ error: 'Session not found' });
    if (s.status === 'open') return res.json({ status: 'already_connected', qr: null });
    if (!s.qrDataUrl) return res.json({ status: 'waiting', qr: null });
    res.json({ status: s.status, qr: s.qrDataUrl });
});
app.post('/api/send', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { number, message } = req.body;
        if (!number || !message) return res.status(400).json({ error: 'number and message required' });
        const result = await s.client.sendMessage(numberToJid(number), message);
        res.json({ success: true, phone_id: pid, data: { id: result.id?._serialized } });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});
app.post('/api/sendGroup', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { group, message } = req.body;
        if (!group || !message) return res.status(400).json({ error: 'group and message required' });
        const jid = resolveGroupJid(group, s.groupCache);
        if (!jid) return res.status(404).json({ error: 'Group not found: ' + group });
        const result = await s.client.sendMessage(jid, message);
        res.json({ success: true, phone_id: pid, data: { id: result.id?._serialized, group_jid: jid } });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});
app.post('/api/send-image', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { number, message, image } = req.body;
        if (!number || !image) return res.status(400).json({ error: 'number and image required' });
        const media = await MessageMedia.fromUrl(image);
        const result = await s.client.sendMessage(numberToJid(number), media, { caption: message || '' });
        res.json({ success: true, phone_id: pid, data: { id: result.id?._serialized } });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});
app.post('/api/delete', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        let mk = req.body.key || req.body._key;
        if (!mk) {
            const { group_id, message_id, participant } = req.body;
            if (!group_id || !message_id) return res.status(400).json({ error: 'Provide key or group_id+message_id' });
            const jid = resolveGroupJid(group_id, s.groupCache);
            if (!jid) return res.status(404).json({ error: 'Group not found: ' + group_id });
            mk = { remoteJid: jid, id: message_id, fromMe: false, participant: participant ? numberToJid(participant) : undefined };
        }
        // Try to find and delete the message in the chat
        const chat = await s.client.getChatById(mk.remoteJid);
        const msgs = await chat.fetchMessages({ limit: 50 });
        const target = msgs.find(m => m.id._serialized === mk.id || m.id.id === mk.id);
        if (target) { await target.delete(true); res.json({ success: true, deleted: mk.id }); }
        else { res.status(404).json({ success: false, error: 'Message not found in recent messages' }); }
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});
app.post('/api/kick', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { group_id, member } = req.body;
        if (!group_id || !member) return res.status(400).json({ error: 'group_id and member required' });
        const jid = resolveGroupJid(group_id, s.groupCache);
        if (!jid) return res.status(404).json({ error: 'Group not found: ' + group_id });
        const chat = await s.client.getChatById(jid);
        const result = await chat.removeParticipants([numberToJid(member)]);
        res.json({ success: true, data: result });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});
app.get('/api/groups', apiAuth, (req, res) => {
    const pid = sanitizeId(req.query.phone_id || '') || getFirstOpenSession();
    const s = sessions.get(pid);
    if (!s) return res.status(404).json({ error: 'Session not found' });
    const groups = [];
    const botJid = pid + '@c.us';
    for (const [jid, meta] of s.groupCache) {
        const participants = meta.participants || [];
        const me = participants.find(p => (p.id && p.id._serialized || p.id || '') === botJid);
        const role = me && me.isSuperAdmin ? 'superadmin' : me && me.isAdmin ? 'admin' : 'member';
        groups.push({ jid, name: meta.subject, participants: participants.length, role });
    }
    res.json({ phone_id: pid, groups });
});
app.get('/api/group-members', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.query.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s) return res.status(404).json({ error: 'Session not found' });
        const groupId = (req.query.group_id || '').trim();
        if (!groupId) return res.status(400).json({ error: 'group_id is required' });
        // Try live fetch first, fall back to cache
        let participants;
        if (s.client && s.status === 'open') {
            try {
                const chat = await s.client.getChatById(groupId);
                participants = chat.participants || [];
            } catch { participants = null; }
        }
        if (!participants) {
            const cached = s.groupCache.get(groupId);
            if (!cached) return res.status(404).json({ error: 'Group not found' });
            participants = cached.participants || [];
        }
        const members = participants.map(p => ({
            number: (p.id?._serialized || p.id || '').replace('@c.us', ''),
            role: p.isSuperAdmin ? 'superadmin' : p.isAdmin ? 'admin' : 'member'
        }));
        res.json({ phone_id: pid, group_id: groupId, total: members.length, members });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});
app.post('/api/refresh-groups', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected' });
        await refreshGroupCacheForSession(pid);
        const groups = [];
        for (const [jid, meta] of s.groupCache) groups.push({ jid, name: meta.subject, participants: meta.participants?.length || 0 });
        res.json({ success: true, phone_id: pid, groups });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});

app.post('/api/leave-group', apiAuth, async (req, res) => {
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

// ─── START ────────────────────────────────────────────────────────────────────
async function main() {
    await initDb();
    app.listen(PORT, () => {
        console.log('[Gateway] Port ' + PORT);
        console.log('[Gateway] http://localhost:' + PORT + '/dashboard');
    });
    await loadSessionsFromDb();
}
main().catch(e => { console.error('[Fatal]', e); process.exit(1); });
