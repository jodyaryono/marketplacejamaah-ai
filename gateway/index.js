import 'dotenv/config';
import express from 'express';
import session from 'express-session';
import nodemailer from 'nodemailer';
process.on('unhandledRejection', (err) => { console.error('[UNHANDLED]', err?.message || err); });
import pkg from 'whatsapp-web.js';
const { Client, LocalAuth, MessageMedia, Location, Buttons, List, Poll } = pkg;
import QRCode from 'qrcode';
import fs from 'fs';
import path from 'path';
import https from 'https';
import os from 'os';
import { randomBytes } from 'crypto';
import { execSync } from 'child_process';
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
const APP_VERSION = '2026.03-10';

// ─── ANTI-SPAM: per-session send rate limiter ─────────────────────────────────
const SEND_DELAY_MS = parseInt(process.env.SEND_DELAY_MS || '5000', 10); // min delay between sends per session (ms)
const SEND_HOURLY_LIMIT = parseInt(process.env.SEND_HOURLY_LIMIT || '150', 10); // max sends per session per hour

const _sendLastTime = new Map();   // phoneId → timestamp of last send
const _sendHourCount = new Map();  // phoneId → { count, resetAt }

/**
 * Per-session send throttle. Ensures minimum gap between outgoing messages
 * and enforces hourly limit. Returns { ok, waitMs, error }.
 */
async function acquireSendSlot(phoneId) {
    // ── hourly limit ──
    const now = Date.now();
    let hc = _sendHourCount.get(phoneId);
    if (!hc || now >= hc.resetAt) {
        hc = { count: 0, resetAt: now + 3600000 };
        _sendHourCount.set(phoneId, hc);
    }
    if (hc.count >= SEND_HOURLY_LIMIT) {
        const minsLeft = Math.ceil((hc.resetAt - now) / 60000);
        return { ok: false, error: `Batas kirim ${SEND_HOURLY_LIMIT} pesan/jam tercapai. Coba lagi dalam ${minsLeft} menit.` };
    }

    // ── minimum delay between sends ──
    const last = _sendLastTime.get(phoneId) || 0;
    const elapsed = now - last;
    if (elapsed < SEND_DELAY_MS) {
        const waitMs = SEND_DELAY_MS - elapsed;
        console.log(`[RateLimit][${phoneId}] Throttle: waiting ${waitMs}ms before send`);
        await new Promise(r => setTimeout(r, waitMs));
    }

    // Reserve slot
    _sendLastTime.set(phoneId, Date.now());
    hc.count++;
    return { ok: true, waitMs: 0 };
}

// ─── ADMIN NOTIFICATION ───────────────────────────────────────────────────────
const NOTIFY_WA = process.env.NOTIFY_WA || '6281317647379';   // nomor WA admin (penerima notif)
const NOTIFY_EMAIL = process.env.NOTIFY_EMAIL || 'me@jodyaryono.id';
const SMTP_HOST = process.env.SMTP_HOST || 'srv180.niagahoster.com';
const SMTP_PORT = parseInt(process.env.SMTP_PORT || '465');
const SMTP_USER = process.env.SMTP_USER || '';
const SMTP_PASS = process.env.SMTP_PASS || '';

const mailer = nodemailer.createTransport({
    host: SMTP_HOST, port: SMTP_PORT, secure: true,
    auth: { user: SMTP_USER, pass: SMTP_PASS },
    tls: { rejectUnauthorized: false },
});

// Debounce per session: avoid spam when WA reconnects quickly
const _notifyDebounce = new Map();

// Verify SMTP on startup
mailer.verify().then(() => console.log('[SMTP] Connection OK →', SMTP_HOST)).catch(e => console.error('[SMTP] Connection FAILED:', e.message));

async function notifyDisconnect(phoneId, label, reason) {
    // Skip if already waiting
    if (_notifyDebounce.has(phoneId)) return;
    _notifyDebounce.set(phoneId, true);
    console.log('[Notify] Disconnect detected:', phoneId, reason, '— waiting 30s before sending...');
    setTimeout(() => {
        // If session recovered within 30s, cancel notification
        const s = sessions.get(phoneId);
        if (s?.status === 'open') {
            console.log('[Notify] Session', phoneId, 'recovered — notification cancelled');
            _notifyDebounce.delete(phoneId);
            return;
        }
        _notifyDebounce.delete(phoneId);
        console.log('[Notify] Session', phoneId, 'still down — sending notification...');
        _sendDisconnectNotif(phoneId, label, reason);
    }, 30000);
}

async function _sendDisconnectNotif(phoneId, label, reason) {
    const timeStr = new Date().toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' });
    const msg = `⚠️ *WhatsApp Terputus*\n\nNomor: *${label || phoneId}* (${phoneId})\nWaktu: ${timeStr} WIB\nAlasan: ${reason || 'unknown'}\n\nSilakan login ulang di https://integrasi-wa.jodyaryono.id`;

    // Send WA notification via first open session
    try {
        const adminSessId = NOTIFY_WA;
        const adminSess = sessions.get(adminSessId);
        if (adminSess?.status === 'open' && adminSess?.client) {
            await adminSess.client.sendMessage(numberToJid(NOTIFY_WA), msg);
            console.log('[Notify] WA sent to', NOTIFY_WA);
        } else {
            // Try any open session
            for (const [, s] of sessions) {
                if (s.status === 'open' && s.client && s !== sessions.get(phoneId)) {
                    await s.client.sendMessage(numberToJid(NOTIFY_WA), msg);
                    console.log('[Notify] WA sent via fallback session');
                    break;
                }
            }
        }
    } catch (e) { console.error('[Notify] WA error:', e.message); }

    // Send Email notification
    try {
        await mailer.sendMail({
            from: `Integrasi WA <${SMTP_USER}>`,
            to: NOTIFY_EMAIL,
            subject: `⚠️ WhatsApp Terputus: ${label || phoneId}`,
            html: `<div style="font-family:sans-serif;max-width:500px">
<h2 style="color:#dc2626">⚠️ WhatsApp Session Terputus</h2>
<table style="border-collapse:collapse;width:100%">
<tr><td style="padding:6px 12px;background:#f3f4f6;font-weight:600">Nomor/Label</td><td style="padding:6px 12px">${label || phoneId}</td></tr>
<tr><td style="padding:6px 12px;background:#f3f4f6;font-weight:600">Phone ID</td><td style="padding:6px 12px">${phoneId}</td></tr>
<tr><td style="padding:6px 12px;background:#f3f4f6;font-weight:600">Waktu</td><td style="padding:6px 12px">${timeStr} WIB</td></tr>
<tr><td style="padding:6px 12px;background:#f3f4f6;font-weight:600">Alasan</td><td style="padding:6px 12px">${reason || 'unknown'}</td></tr>
</table>
<p style="margin-top:20px"><a href="https://integrasi-wa.jodyaryono.id" style="background:#16a34a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:600">Login &amp; Reconnect</a></p>
</div>`,
        });
        console.log('[Notify] Email sent to', NOTIFY_EMAIL);
    } catch (e) { console.error('[Notify] Email error:', e.message); }
}

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
    // Create indexes for performance (safe to run multiple times)
    await db.query(`
        CREATE INDEX IF NOT EXISTS idx_messages_log_session_created ON messages_log(session_id, created_at DESC);
        CREATE INDEX IF NOT EXISTS idx_messages_log_created ON messages_log(created_at DESC);
        CREATE INDEX IF NOT EXISTS idx_broadcast_jobs_status ON broadcast_jobs(status, created_at DESC);
    `);
    // Retention: delete messages_log rows older than 30 days
    const deleted = await db.query(`DELETE FROM messages_log WHERE created_at < NOW() - INTERVAL '30 days'`);
    if (deleted.rowCount > 0) console.log('[DB] Retention: pruned ' + deleted.rowCount + ' old message_log rows');
    console.log('[DB] Tables ready');
}

// ─── LOGIN BRUTE-FORCE PROTECTION ─────────────────────────────────────────────
const _loginFailMap = new Map(); // ip → { count, lockedUntil }
function checkLoginRateLimit(ip) {
    const now = Date.now();
    let entry = _loginFailMap.get(ip);
    if (!entry) { entry = { count: 0, lockedUntil: 0 }; _loginFailMap.set(ip, entry); }
    if (now < entry.lockedUntil) {
        const secsLeft = Math.ceil((entry.lockedUntil - now) / 1000);
        return { allowed: false, secsLeft };
    }
    return { allowed: true };
}
function recordLoginFail(ip) {
    const now = Date.now();
    let entry = _loginFailMap.get(ip);
    if (!entry) { entry = { count: 0, lockedUntil: 0 }; _loginFailMap.set(ip, entry); }
    entry.count++;
    if (entry.count >= 5) { entry.lockedUntil = now + 5 * 60 * 1000; entry.count = 0; } // lock 5 min after 5 failures
}
function recordLoginSuccess(ip) { _loginFailMap.delete(ip); }

// ─── MULTI-SESSION STATE ──────────────────────────────────────────────────────
const sessions = new Map();

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function sanitizeId(id) { return String(id).replace(/[^a-zA-Z0-9_-]/g, ''); }
const withTimeout = (p, ms, msg = 'timeout') => Promise.race([p, new Promise((_, rej) => setTimeout(() => rej(new Error(msg)), ms))]);
function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
function getFirstOpenSession() {
    for (const [id, s] of sessions) if (s.status === 'open') return id;
    return '';
}
function numberToJid(number) {
    let num = String(number).replace(/[\s\-\(\)]/g, '');
    if (num.startsWith('+')) num = num.slice(1);
    num = num.replace(/\D/g, '');
    if (num.startsWith('0')) num = '62' + num.slice(1);
    else if (num.startsWith('8') && num.length >= 9 && num.length <= 13) num = '62' + num;
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
function hasValidAuth(phoneId) {
    const id = sanitizeId(phoneId);
    const markerFile = path.join('./auth_info', 'session-' + id, '.paired');
    return fs.existsSync(markerFile);
}

function isChromeDeadError(err) {
    const msg = err?.message || '';
    return msg.includes('Protocol error') || msg.includes('Target closed') || msg.includes('Session closed') || msg.includes('detached') || msg.includes('Execution context') || msg.includes('frame was');
}
function handleDeadSession(pid, sess, err) {
    if (!isChromeDeadError(err)) return false;
    console.log('[WA][' + pid + '] Chrome dead detected (' + (err?.message || '') + '), triggering reconnect...');
    if (sess) {
        killZombieChrome(pid);
        sess.status = 'disconnected';
        sess.client = null;
        sess.connectedAt = null;
        sess._failCount = 0;
        if (sess._reconnectTimer) { clearTimeout(sess._reconnectTimer); sess._reconnectTimer = null; }
        startSession(pid, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled);
    }
    return true;
}

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
    if (msg.type === 'location' || msg.type === 'live_location') return 'locationMessage';
    if (msg.hasMedia) {
        if (msg.type === 'image') return 'imageMessage';
        if (msg.type === 'video') return 'videoMessage';
        if (msg.type === 'audio' || msg.type === 'ptt') return 'audioMessage';
        if (msg.type === 'document') return 'documentMessage';
        if (msg.type === 'sticker') return 'stickerMessage';
        return msg.type + 'Message';
    }
    if (msg.type === 'vcard' || msg.type === 'multi_vcard') return 'contactMessage';
    return 'conversation';
}

// ─── WEBHOOK ──────────────────────────────────────────────────────────────────
async function forwardToWebhook(msg, phoneId, groupCache) {
    const jid = msg.from;
    const isGroup = jid?.endsWith('@g.us');
    const contentType = getContentType(msg);
    const textContent = msg.body || '';
    const cleanJid = (j) => j ? j.replace(/@(c\.us|s\.whatsapp\.net|lid|newsletter|broadcast|g\.us)$/i, '') : '';

    // Resolve the real phone number — contact.number is always a phone number
    // even when WhatsApp uses LID (@lid) format for the JID.
    let fromNum;
    let pushName = null;
    try {
        const contact = await msg.getContact();
        pushName = contact?.pushname || contact?.name || null;
        fromNum = contact?.number || (isGroup ? cleanJid(msg.author || '') : cleanJid(jid));
    } catch {
        fromNum = isGroup ? cleanJid(msg.author || '') : cleanJid(jid);
    }

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
                    if (rule.is_regex) { try { if (rule.keyword.length <= 200) { const _re = new RegExp(rule.keyword, 'i'); matched = _re.test(textContent.slice(0, 2000)); } } catch { } }
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
        const payload = {
            phone_id: phoneId, message_id: msg.id?._serialized, message: textContent,
            type: contentType?.replace('Message', '') || 'text',
            timestamp: msg.timestamp || Math.floor(Date.now() / 1000),
            sender: fromNum, sender_name: pushName, from: isGroup ? cleanJid(jid) : fromNum,
            pushname: pushName,
            ...(msg.location ? { location: { latitude: msg.location.latitude, longitude: msg.location.longitude, description: msg.location.description || '', url: msg.location.url || '' } } : {}),
            ...(isGroup ? { group_id: jid, from_group: jid, group_name: groupCache.get(jid)?.subject || jid } : {}),
            _key: { remoteJid: jid, id: msg.id?._serialized, fromMe: msg.fromMe || false, participant: msg.author || undefined },
        };
        const resp = await fetch(wUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Webhook-Secret': process.env.WEBHOOK_SECRET || '' }, body: JSON.stringify(payload) });
        console.log('[Webhook][' + phoneId + '] ' + resp.status);
    } catch (e) { console.error('[Webhook][' + phoneId + ']', e.message); }
}

// ─── SESSION MANAGEMENT ───────────────────────────────────────────────────────
function killZombieChrome(sessionId) {
    const dataDir = path.resolve('./auth_info', 'session-' + sessionId);
    try {
        // Step 1: Find PIDs holding files in the session directory
        let pids = new Set();
        try {
            const fuserOut = execSync(`fuser -v "${dataDir}/SingletonLock" 2>/dev/null || true`, { timeout: 5000, encoding: 'utf8' });
            for (const m of fuserOut.matchAll(/(\d+)/g)) pids.add(m[1]);
        } catch { }
        // Step 2: Find PIDs via command-line match (catches renderer processes too)
        try {
            const pgrepOut = execSync(`pgrep -f 'user-data-dir=${dataDir.replace(/'/g, "'\\''")}'`, { timeout: 5000, encoding: 'utf8' });
            for (const line of pgrepOut.trim().split('\n')) { if (line.trim()) pids.add(line.trim()); }
        } catch { /* pgrep returns 1 if no match */ }
        // Step 3: Kill all found PIDs — SIGTERM first (let Chrome flush data), then SIGKILL
        if (pids.size > 0) {
            console.log('[WA][' + sessionId + '] Killing ' + pids.size + ' zombie Chrome PIDs: ' + [...pids].join(','));
            // Graceful SIGTERM first — Chrome can save LevelDB/IndexedDB session data
            for (const pid of pids) { try { execSync('kill -15 ' + pid + ' 2>/dev/null', { timeout: 2000 }); } catch { } }
            // Wait 3 seconds for graceful shutdown
            try { execSync('sleep 3', { timeout: 5000 }); } catch { }
            // SIGKILL any survivors
            for (const pid of pids) { try { execSync('kill -9 ' + pid + ' 2>/dev/null', { timeout: 2000 }); } catch { } }
        }
        // Step 4: Always clean lock files even if no PIDs found
        for (const f of ['SingletonLock', 'SingletonCookie', 'SingletonSocket', 'DevToolsActivePort']) {
            try { fs.unlinkSync(path.join(dataDir, f)); } catch { }
        }
    } catch (e) { console.error('[WA][' + sessionId + '] killZombieChrome error:', e.message); }
}
async function safeDestroy(client, id, timeoutMs = 8000) {
    if (!client) return;
    try {
        await Promise.race([client.destroy(), new Promise(r => setTimeout(r, timeoutMs))]);
    } catch { }
    // Always kill zombie after destroy attempt — client.destroy() often leaves orphans
    killZombieChrome(id);
}
const _sessionStartLocks = new Set(); // prevent concurrent startSession for same id

async function startSession(phoneId, label = '', apiToken = '', webhookUrl = '', webhookEnabled = false, createdAt = null) {
    const id = sanitizeId(phoneId);
    if (!id) return;
    // ── Race condition guard: skip if already starting ──
    if (_sessionStartLocks.has(id)) { console.log('[WA][' + id + '] startSession already in progress, skipping'); return; }
    _sessionStartLocks.add(id);
    try {
        await _startSessionInternal(id, label, apiToken, webhookUrl, webhookEnabled, createdAt);
    } finally {
        _sessionStartLocks.delete(id);
    }
}
async function _startSessionInternal(id, label, apiToken, webhookUrl, webhookEnabled, createdAt) {
    const existing = sessions.get(id);
    if (existing?.status === 'open') return;
    // Allow retry if connecting for too long (stuck > 2 min)
    if (existing?.status === 'connecting' && existing?.client) {
        const stuckMs = existing._connectingSince ? Date.now() - existing._connectingSince : 0;
        if (stuckMs < 120000) return;
        console.log('[WA][' + id + '] Stuck connecting for ' + Math.round(stuckMs / 1000) + 's, force retry');
        await safeDestroy(existing.client, id);
        existing.client = null;
    }
    if (existing?._reconnectTimer) { clearTimeout(existing._reconnectTimer); existing._reconnectTimer = null; }
    if (existing?.client) { await safeDestroy(existing.client, id); existing.client = null; } else { killZombieChrome(id); }

    const sess = { client: null, qrCode: null, qrDataUrl: null, status: 'connecting', _connectingSince: Date.now(), groupCache: existing?.groupCache || new Map(), label: label || existing?.label || id, apiToken: apiToken || existing?.apiToken || '', webhookUrl: webhookUrl || existing?.webhookUrl || '', webhookEnabled: webhookEnabled !== undefined ? webhookEnabled : (existing?.webhookEnabled || false), _reconnectTimer: null, _failCount: existing?._failCount || 0, _qrTimeoutCount: existing?._qrTimeoutCount || 0, _qrCount: existing?._qrCount || 0, createdAt: createdAt || existing?.createdAt || null, connectedAt: null };
    sessions.set(id, sess);

    const puppeteerArgs = [
        '--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
        '--disable-gpu', '--no-first-run', '--no-zygote',
        '--disk-cache-size=1', '--media-cache-size=1',
        '--disable-extensions', '--disable-plugins', '--disable-sync', '--disable-translate',
        '--js-flags=--max-old-space-size=384', '--aggressive-cache-discard', '--disable-cache',
        '--disable-software-rasterizer',
        '--disable-logging', '--disable-background-networking',
        '--disable-default-apps', '--disable-hang-monitor',
        '--disable-popup-blocking', '--disable-prompt-on-repost',
        '--disable-client-side-phishing-detection', '--disable-component-update',
        '--disable-domain-reliability', '--disable-features=AudioServiceOutOfProcess',
        '--renderer-process-limit=1', '--disable-renderer-backgrounding',
    ];
    const appSettings = await getAppSettings();
    const client = new Client({
        authStrategy: new LocalAuth({ clientId: id, dataPath: './auth_info' }),
        puppeteer: { headless: true, args: puppeteerArgs, protocolTimeout: 300000, timeout: 120000, ...(CHROME_PATH ? { executablePath: CHROME_PATH } : {}) },
        deviceName: appSettings.device_name || 'Integrasi-wa.jodyaryono.id',
        browserName: appSettings.browser_name || 'Google Chrome',
    });
    sess.client = client;

    client.on('qr', async (qr) => {
        sess.qrCode = qr;
        sess.qrDataUrl = await QRCode.toDataURL(qr);
        sess.status = 'connecting';
        sess._qrCount = (sess._qrCount || 0) + 1;
        // If QR appears but .paired exists, auth is stale — remove .paired marker
        if (sess._qrCount === 1) {
            const pairedFile = path.join('./auth_info', 'session-' + id, '.paired');
            if (fs.existsSync(pairedFile)) {
                console.log('[WA][' + id + '] QR muncul tapi .paired ada — auth stale, hapus .paired');
                try { fs.unlinkSync(pairedFile); } catch { }
            }
        }
        const qrLimit = 15;
        console.log('[WA][' + id + '] QR ready (' + sess._qrCount + '/' + qrLimit + ')');
        // Kill Chrome if QR not scanned after qrLimit cycles (~5 min) to save RAM
        if (sess._qrCount >= qrLimit) {
            sess._qrTimeoutCount = (sess._qrTimeoutCount || 0) + 1;
            console.log('[WA][' + id + '] QR timeout #' + sess._qrTimeoutCount + ' — destroying Chrome to save memory');
            sess._qrTimeout = true; // prevent disconnected event from auto-reconnecting
            sess.status = 'disconnected';
            sess.qrCode = null;
            sess.qrDataUrl = null;
            sess._qrCount = 0;
            await safeDestroy(client, id);
            sess.client = null;
            // QR session expired without scan — STOP and release Chrome to save RAM.
            // User must click Reconnect from dashboard to try again.
            if (!sess._removing && !_shuttingDown) {
                console.log('[WA][' + id + '] QR expired tanpa scan — Chrome dihentikan. User harus klik Reconnect dari dashboard.');
            }
        }
    });
    client.on('ready', async () => {
        sess.status = 'open';
        sess.qrCode = null;
        sess.qrDataUrl = null;
        sess._failCount = 0;
        sess._hbFails = 0;
        sess._qrCount = 0;
        sess._qrTimeoutCount = 0;
        if (sess._readyTimer) { clearTimeout(sess._readyTimer); sess._readyTimer = null; }
        sess.connectedAt = new Date();
        // Mark session as successfully paired
        try { fs.writeFileSync(path.join('./auth_info', 'session-' + id, '.paired'), new Date().toISOString()); } catch { }
        console.log('[WA][' + id + '] Connected ✅ (ready event fired)');
        refreshGroupCacheForSession(id);
    });
    client.on('authenticated', () => { console.log('[WA][' + id + '] Authenticated (loading WA Web data...)'); sess._authenticated = true; });
    client.on('auth_failure', (msg) => {
        console.error('[WA][' + id + '] Auth failure:', msg);
        sess.status = 'disconnected'; sess.client = null; sess.connectedAt = null;
        notifyDisconnect(id, sess.label, 'Auth failure: ' + msg);
        if (!sess._removing && !sess._userDisconnecting) {
            sess._failCount = (sess._failCount || 0) + 1;
            const sessionDir = path.join('./auth_info', 'session-' + id);
            const wasPaired = fs.existsSync(path.join(sessionDir, '.paired'));
            // If was paired before, DON'T delete auth yet — LevelDB corruption may self-heal on retry
            // Only delete after 3+ consecutive auth failures
            if (wasPaired && sess._failCount < 3) {
                const delay = 20000;
                console.log('[WA][' + id + '] Auth failed but was previously paired — retry with existing auth in ' + (delay / 1000) + 's (attempt ' + sess._failCount + '/3)');
                if (!sess._reconnectTimer) {
                    sess._reconnectTimer = setTimeout(() => { sess._reconnectTimer = null; startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled); }, delay);
                }
            } else {
                // Auth truly invalid — clear and show QR
                try { fs.rmSync(sessionDir, { recursive: true, force: true }); } catch { }
                if (wasPaired) {
                    console.log('[WA][' + id + '] ⚠️ Auth deleted after ' + sess._failCount + ' failures — USER HARUS HAPUS LINKED DEVICE "' + (sess.label || id) + '" DI HP, lalu scan QR baru');
                }
                const delay = 15000;
                console.log('[WA][' + id + '] Auth failed — will start QR for re-pair in ' + (delay / 1000) + 's');
                sess._failCount = 0;
                if (!sess._reconnectTimer) {
                    sess._reconnectTimer = setTimeout(() => { sess._reconnectTimer = null; startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled); }, delay);
                }
            }
        }
    });
    client.on('disconnected', (reason) => {
        console.log('[WA][' + id + '] Disconnected:', reason);
        sess.status = 'disconnected';
        sess.client = null;
        sess.connectedAt = null;
        // === ONLY these 2 cases skip reconnect entirely ===
        // 1. Session is being DELETED from dashboard
        if (sess._removing) { console.log('[WA][' + id + '] Session removed, skip reconnect.'); return; }
        // 2. User clicked DISCONNECT from dashboard
        if (sess._userDisconnecting) {
            console.log('[WA][' + id + '] User-initiated disconnect, skip reconnect.');
            sess._userDisconnecting = false;
            return;
        }
        // === Server shutting down — will restore on next startup ===
        if (_shuttingDown) { console.log('[WA][' + id + '] Server shutting down, skip reconnect.'); return; }
        // === QR timeout — already handled by auto-restart in qr handler ===
        if (sess._qrTimeout) { console.log('[WA][' + id + '] QR timeout disconnect (auto-restart scheduled).'); sess._qrTimeout = false; return; }
        // Notify admin (email + WA) with debounce
        notifyDisconnect(id, sess.label, reason);
        // LOGOUT = phone unlinked device — clear invalid auth, then start QR for re-pair
        if (reason === 'LOGOUT') {
            console.log('[WA][' + id + '] LOGOUT from phone — clearing auth, auto-starting QR for re-pair.');
            const sessionDir = path.join('./auth_info', 'session-' + id);
            try { fs.rmSync(sessionDir, { recursive: true, force: true }); } catch { }
            sess._failCount = 0;
            const delay = 15000;
            console.log('[WA][' + id + '] Will show QR in ' + (delay / 1000) + 's');
            sess._reconnectTimer = setTimeout(() => { sess._reconnectTimer = null; startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled); }, delay);
            return;
        }
        // Normal disconnect — reconnect with auth if available, or start QR if no auth
        sess._failCount = (sess._failCount || 0) + 1;
        const sessionDir = path.join('./auth_info', 'session-' + id);
        const hasAuth = fs.existsSync(sessionDir);
        const delay = hasAuth
            ? (sess._failCount >= 3 ? 5 * 60 * 1000 : 20000)
            : (sess._failCount >= 3 ? 2 * 60 * 1000 : 30000);
        console.log('[WA][' + id + '] Auto-reconnect in ' + (delay / 1000) + 's (attempt ' + sess._failCount + ', auth=' + (hasAuth ? 'yes' : 'no-will-show-QR') + ')');
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

    // group_join: someone was directly added or joined via invite link
    client.on('group_join', async (notification) => {
        try {
            const groupJid = notification.chatId;
            const participants = notification.recipientIds || [];
            const chat = await notification.getChat();
            const groupName = chat && chat.name ? chat.name : groupJid;
            const sessObj = sessions.get(id);
            const wUrl = sessObj && sessObj.webhookUrl ? sessObj.webhookUrl : WEBHOOK_URL;
            const wEnabled = sessObj && sessObj.webhookEnabled !== undefined ? sessObj.webhookEnabled : WEBHOOK_ENABLED;
            if (!wUrl || !wEnabled) return;
            const payload = {
                phone_id: id,
                type: 'group_participants_update',
                action: 'add',
                group_id: groupJid,
                group_name: groupName,
                participants: participants.map(function(p) { return p.replace(/@\S+/g, ''); }),
                timestamp: Math.floor(Date.now() / 1000),
            };
            await fetch(wUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Webhook-Secret': process.env.WEBHOOK_SECRET || '' },
                body: JSON.stringify(payload),
            });
            console.log('[GroupJoin][' + id + '] ' + participants.length + ' member(s) added to ' + groupName);
        } catch (e) { console.error('[GroupJoin][' + id + ']', e.message); }
    });

    // group_leave: someone left or was removed from the group
    client.on('group_leave', async (notification) => {
        try {
            const groupJid = notification.chatId;
            const participants = notification.recipientIds || [];
            const chat = await notification.getChat();
            const groupName = chat && chat.name ? chat.name : groupJid;
            const sessObj = sessions.get(id);
            const wUrl = sessObj && sessObj.webhookUrl ? sessObj.webhookUrl : WEBHOOK_URL;
            const wEnabled = sessObj && sessObj.webhookEnabled !== undefined ? sessObj.webhookEnabled : WEBHOOK_ENABLED;
            if (!wUrl || !wEnabled) return;
            const payload = {
                phone_id: id,
                type: 'group_participants_update',
                action: 'remove',
                group_id: groupJid,
                group_name: groupName,
                participants: participants.map(function(p) { return p.replace(/@\S+/g, ''); }),
                timestamp: Math.floor(Date.now() / 1000),
            };
            await fetch(wUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Webhook-Secret': process.env.WEBHOOK_SECRET || '' },
                body: JSON.stringify(payload),
            });
            console.log('[GroupLeave][' + id + '] ' + participants.length + ' member(s) left ' + groupName);
        } catch (e) { console.error('[GroupLeave][' + id + ']', e.message); }
    });

    // group_membership_request: someone requested to join a closed/locked group
    client.on('group_membership_request', async (notification) => {
        try {
            const groupJid = notification.chatId;
            const requesterJid = (notification.recipientIds || [])[0] || '';
            const requesterPhone = requesterJid.replace(/@\S+/g, '');
            const chat = await notification.getChat();
            const groupName = chat && chat.name ? chat.name : groupJid;
            const sessObj = sessions.get(id);
            const wUrl = sessObj && sessObj.webhookUrl ? sessObj.webhookUrl : WEBHOOK_URL;
            const wEnabled = sessObj && sessObj.webhookEnabled !== undefined ? sessObj.webhookEnabled : WEBHOOK_ENABLED;
            if (!wUrl || !wEnabled) return;
            const payload = {
                phone_id: id,
                type: 'group_membership_request',
                group_id: groupJid,
                group_name: groupName,
                requester: requesterPhone,
                requester_jid: requesterJid,
                timestamp: Math.floor(Date.now() / 1000),
            };
            await fetch(wUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Webhook-Secret': process.env.WEBHOOK_SECRET || '' },
                body: JSON.stringify(payload),
            });
            console.log('[MembershipReq][' + id + '] ' + requesterPhone + ' requested to join ' + groupName);
        } catch (e) { console.error('[MembershipReq][' + id + ']', e.message); }
    });
    try {
        const initTimeout = new Promise((_, rej) => setTimeout(() => rej(new Error('TIMEOUT: initialize took too long (180s)')), 180000));
        await Promise.race([client.initialize(), initTimeout]);
        console.log('[WA][' + id + '] Initialize resolved — waiting for ready event...');
        // Post-init guard: if ready event doesn't fire after initialize, force restart
        // Wait longer (300s) if authenticated fired (auth valid, WA Web slow to sync)
        // Wait shorter (120s) if not authenticated (something wrong)
        if (sess.status !== 'open') {
            const readyWaitMs = 120000; // first check at 120s
            sess._readyTimer = setTimeout(async () => {
                sess._readyTimer = null;
                if (sess.status === 'open' || sess._removing || _shuttingDown) return;
                // Skip if QR is actively showing — QR timeout handles that separately
                if (sess._qrCount > 0) return;
                if (sess._authenticated) {
                    // Authenticated but ready not fired — WA Web may be slow. Give 180s more.
                    console.log('[WA][' + id + '] Authenticated but ready not fired after 120s — waiting 180s more...');
                    sess._readyTimer = setTimeout(async () => {
                        sess._readyTimer = null;
                        if (sess.status === 'open' || sess._removing || _shuttingDown) return;
                        console.log('[WA][' + id + '] Ready NOT fired after 300s total — auth mungkin stale, hapus auth dan restart');
                        await safeDestroy(client, id);
                        sess.client = null;
                        sess.status = 'disconnected';
                        sess.connectedAt = null;
                        // Delete stale auth — will need QR re-scan
                        const sessionDir = path.join('./auth_info', 'session-' + id);
                        try { fs.rmSync(sessionDir, { recursive: true, force: true }); } catch { }
                        console.log('[WA][' + id + '] Auth dihapus — perlu scan QR ulang dari dashboard');
                        sess._failCount = 0;
                    }, 180000);
                } else {
                    console.log('[WA][' + id + '] Ready event not fired 120s after initialize (no auth) — force restart');
                    await safeDestroy(client, id);
                    sess.client = null;
                    sess.status = 'disconnected';
                    sess.connectedAt = null;
                    sess._failCount = (sess._failCount || 0) + 1;
                    const delay = sess._failCount >= 3 ? 5 * 60 * 1000 : 30000;
                    if (!sess._reconnectTimer) {
                        sess._reconnectTimer = setTimeout(() => { sess._reconnectTimer = null; startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled); }, delay);
                    }
                }
            }, readyWaitMs);
        }
    } catch (e) {
        console.error('[WA][' + id + '] Initialize error:', e.message);
        await safeDestroy(client, id);
        sess.status = 'disconnected';
        sess.client = null;
        sess.connectedAt = null;
        const isCrash = e.message?.includes('Protocol error') || e.message?.includes('Session closed') || e.message?.includes('Target closed') || e.message?.includes('pipe') || e.message?.includes('Navigating frame') || e.message?.includes('detached') || e.message?.includes('frame was') || e.message?.includes('TIMEOUT') || e.message?.includes('Execution context was destroyed');
        const sessionDir = path.join('./auth_info', 'session-' + id);
        if (isCrash && fs.existsSync(sessionDir)) {
            // Chrome crashed or timing issue — auth data is fine, just retry
            sess._failCount = (sess._failCount || 0) + 1;
            const delay = sess._failCount >= 3 ? 5 * 60 * 1000 : 30000;
            console.log('[WA][' + id + '] Chrome crash/timing error, retry in ' + (delay / 1000) + 's (attempt ' + sess._failCount + ')');
            if (!sess._reconnectTimer) {
                sess._reconnectTimer = setTimeout(() => { sess._reconnectTimer = null; startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled); }, delay);
            }
            notifyDisconnect(id, sess.label, 'Initialize error: ' + e.message);
        } else {
            // Unknown init error — NEVER delete auth data, just retry
            sess._failCount = (sess._failCount || 0) + 1;
            const delay = sess._failCount >= 3 ? 5 * 60 * 1000 : 30000;
            console.log('[WA][' + id + '] Init error (' + e.message + '), retry in ' + (delay / 1000) + 's (attempt ' + sess._failCount + ')');
            if (!sess._reconnectTimer) {
                sess._reconnectTimer = setTimeout(() => { sess._reconnectTimer = null; startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled); }, delay);
            }
            notifyDisconnect(id, sess.label, 'Init error: ' + e.message);
        }
    }
} // end _startSessionInternal

async function refreshGroupCacheForSession(phoneId) {
    const sess = sessions.get(phoneId);
    if (!sess?.client) return;
    try {
        const chats = await withTimeout(sess.client.getChats(), 30000, 'getChats timeout');
        const groups = chats.filter(c => c.isGroup);
        for (const g of groups) sess.groupCache.set(g.id._serialized, { subject: g.name, participants: g.participants || [] });
        console.log('[WA][' + phoneId + '] Cached ' + sess.groupCache.size + ' groups');
    } catch (e) { console.error('[WA][' + phoneId + '] Group fetch:', e.message); }
}

async function removeSession(phoneId) {
    const id = sanitizeId(phoneId);
    const sess = sessions.get(id);
    const withTimeout = (p, ms) => Promise.race([p, new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), ms))]);
    if (sess) sess._removing = true;
    if (sess?._reconnectTimer) { clearTimeout(sess._reconnectTimer); sess._reconnectTimer = null; }
    if (sess?.client) {
        if (sess.status === 'open') { try { await withTimeout(sess.client.logout(), 8000); } catch { } }
        await safeDestroy(sess.client, id);
    } else { killZombieChrome(id); }
    sessions.delete(id);
    try { fs.rmSync(path.join('./auth_info', 'session-' + id), { recursive: true, force: true }); } catch { }
    try { fs.rmSync(getSessionAuthDir(id), { recursive: true, force: true }); } catch { }
    try { await db.query('DELETE FROM wa_sessions WHERE phone_id=$1', [id]); } catch { }
}

async function loadSessionsFromDb() {
    try {
        const { rows } = await db.query('SELECT phone_id, label, api_token, webhook_url, webhook_enabled, created_at FROM wa_sessions ORDER BY created_at');
        const toRestore = [];
        const needPairing = [];
        for (const row of rows) {
            if (!row.api_token) {
                row.api_token = randomBytes(16).toString('hex');
                await db.query('UPDATE wa_sessions SET api_token=$1 WHERE phone_id=$2', [row.api_token, row.phone_id]);
            }
            const sessionDir = path.join('./auth_info', 'session-' + sanitizeId(row.phone_id));
            if (hasValidAuth(row.phone_id)) {
                toRestore.push(row);
            } else {
                console.log('[Gateway] Need pairing (no valid auth): ' + row.phone_id);
                // Clean up empty/incomplete session dirs
                if (fs.existsSync(sessionDir)) { try { fs.rmSync(sessionDir, { recursive: true, force: true }); } catch { } }
                sessions.set(sanitizeId(row.phone_id), { client: null, qrCode: null, qrDataUrl: null, status: 'disconnected', groupCache: new Map(), label: row.label || row.phone_id, apiToken: row.api_token, webhookUrl: row.webhook_url || '', webhookEnabled: row.webhook_enabled || false, _reconnectTimer: null, createdAt: row.created_at || null, connectedAt: null });
                needPairing.push(row);
            }
        }
        // Stagger session restores: 10s apart to avoid RAM spike from simultaneous Chrome launches
        for (let i = 0; i < toRestore.length; i++) {
            const row = toRestore[i];
            if (i > 0) await new Promise(r => setTimeout(r, 10000));
            console.log('[Gateway] Restoring (' + (i + 1) + '/' + toRestore.length + '): ' + row.phone_id);
            startSession(row.phone_id, row.label, row.api_token, row.webhook_url || '', row.webhook_enabled || false, row.created_at);
        }
        // Don't auto-start QR sessions at boot — they waste RAM (each Chrome ~500MB).
        // User must click Reconnect from dashboard to start QR pairing.
        if (needPairing.length > 0) {
            console.log('[Gateway] ' + needPairing.length + ' session(s) need pairing — waiting for user to start QR from dashboard: ' + needPairing.map(r => r.phone_id).join(', '));
        }
    } catch (e) { console.error('[DB] Load sessions failed:', e.message); }
}

// ─── EXPRESS ──────────────────────────────────────────────────────────────────
const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(session({ secret: SESSION_SECRET, resave: false, saveUninitialized: false, cookie: { secure: process.env.NODE_ENV === 'production', sameSite: 'lax', maxAge: 1000 * 60 * 60 * 8 } }));

function apiAuth(req, res, next) {
    const header = req.headers.authorization || '';
    const token = header.startsWith('Bearer ') ? header.slice(7) : req.body?.token || req.query?.token;
    if (!token) return res.status(401).json({ error: 'Unauthorized' });
    if (AUTH_TOKEN && token === AUTH_TOKEN) return next();
    for (const [, s] of sessions) {
        if (s.apiToken && s.apiToken === token) return next();
    }
    return res.status(401).json({ error: 'Unauthorized' });
}
function requireLogin(req, res, next) {
    if (req.session?.loggedIn) return next();
    if (req.xhr || req.headers.accept?.includes('application/json') || req.headers['content-type']?.includes('application/json') || (req.headers.accept && !req.headers.accept.includes('text/html')))
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
        '  <div class="sidebar-logo"><h1>📱 Integrasi WA</h1><p>WhatsApp Gateway</p><span style="font-size:.65rem;color:#6ee7b7;font-family:monospace;letter-spacing:.5px">v' + APP_VERSION + '</span></div>\n' +
        '  <nav class="sidebar-nav">\n' +
        ni('/dashboard', '🏠', 'Dashboard', 'dashboard') +
        ni('/logout', '🚪', 'Logout', 'logout') +
        ncGroup('Setting',
            nci('/dashboard', '📱', 'Device', 'device') +
            nci('/settings', '⚙️', 'General Settings', 'general-settings') +
            nci('/api-manual', '📖', 'API Manual', 'api-manual') +
            nci('/release-notes', '📋', 'Release Notes', 'release-notes')
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
    let err = '';
    if (req.query.error === '2') err = '<div class="alert-err">⛔ Terlalu banyak percobaan. Coba lagi dalam ' + (req.query.wait || 300) + ' detik.</div>';
    else if (req.query.error) err = '<div class="alert-err">❌ Username atau password salah.</div>';
    res.send('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login — Integrasi WA</title><style>' + CSS + '</style></head><body><div class="lp"><div class="lcard"><div class="llogo"><span class="ico">📱</span><h1>Integrasi WA</h1><p>WhatsApp Gateway — Multi Nomor</p></div>' + err + '<form method="POST" action="/login"><div class="form-group"><label>Username</label><input class="input" type="text" name="username" placeholder="admin" autocomplete="username" required autofocus></div><div class="form-group"><label>Password</label><input class="input" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required></div><button class="btn btn-primary" style="width:100%;padding:11px;font-size:.95rem;justify-content:center;" type="submit">Masuk</button></form></div></div></body></html>');
});
app.post('/login', (req, res) => {
    const ip = req.ip || req.connection.remoteAddress || 'unknown';
    const rl = checkLoginRateLimit(ip);
    if (!rl.allowed) return res.redirect('/login?error=2&wait=' + rl.secsLeft);
    const { username, password } = req.body;
    if (username === ADMIN_USER && password === ADMIN_PASS) {
        recordLoginSuccess(ip);
        req.session.loggedIn = true;
        return res.redirect('/dashboard');
    }
    recordLoginFail(ip);
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

    const serverPanel =
        '<div class="card" style="margin-bottom:18px">' +
        '<div class="card-header"><span class="card-title">🖥️ Server & Service</span></div>' +
        '<div style="padding:16px">' +
        '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px" id="srv-info"><div style="color:#6b7280;font-size:.85rem">Memuat info server…</div></div>' +
        '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">' +
        '<button class="btn btn-primary btn-sm" onclick="srvAction(\'restart\')" id="srv-restart-btn">🔄 Restart Service</button>' +
        '<button class="btn btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca" onclick="srvAction(\'stop\')" id="srv-stop-btn">⏹ Stop Service</button>' +
        '<span id="srv-action-result" style="font-size:.85rem"></span>' +
        '</div></div></div>';

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
        '  try{const r=await fetch("/web/sessions");if(r.status===401){window.location.href="/login";return;}if(!r.ok)return;const d=await r.json();' +
        '  const ss=d.sessions;' +
        '  document.getElementById("st-total").textContent=ss.length;' +
        '  document.getElementById("st-conn").textContent=ss.filter(x=>x.status==="open").length;' +
        '  document.getElementById("st-cing").textContent=ss.filter(x=>x.status==="connecting").length;' +
        '  document.getElementById("st-disc").textContent=ss.filter(x=>x.status==="disconnected").length;' +
        '  const tb=document.getElementById("sess-tbody");' +
        '  if(!ss.length){tb.innerHTML=\'<tr class="empty-row"><td colspan="5">Belum ada nomor.</td></tr>\';return;}' +
        '  tb.innerHTML=ss.map(s=>{' +
        '    const bc=s.status==="open"?"badge-open":s.status==="connecting"?"badge-conn":"badge-disc";' +
        '    const bl=s.status==="open"?"Terhubung":s.status==="connecting"?"Menghubungkan":(!s.paired?"Belum Terpasang":"Terputus");' +
        '    const regDate=s.created_at?new Date(s.created_at).toLocaleDateString("id-ID",{day:"2-digit",month:"short",year:"numeric"}):"—";' +
        '    const connUptime=(()=>{if(s.status!=="open"||!s.connected_at)return"";const ms=Date.now()-new Date(s.connected_at).getTime();const h=Math.floor(ms/3600000);const m=Math.floor((ms%3600000)/60000);return h>0?h+"j "+m+"m":m+"m";})();' +
        '    const qr=s.status!=="open"?(""+( s.paired?\'<button class="btn btn-ghost btn-sm" style="background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;" data-recid="\'+esc(s.phone_id)+\'" onclick="reconn(this.dataset.recid)">🔄 Reconnect</button>\':"" )+\'<button class="btn btn-ghost btn-sm" data-qrid="\'+esc(s.phone_id)+\'" onclick="openQR(this.dataset.qrid)">📷 Scan QR</button>\'+\'<button class="btn btn-ghost btn-sm" style="background:#f0f9ff;color:#0369a1;border-color:#bae6fd;" data-pairid="\'+esc(s.phone_id)+\'" onclick="openPairing(this.dataset.pairid)">🔑 Kode Pairing</button>\'):(\'<span style="color:#10b981;font-size:.8rem;">✅ Online</span><button class="btn btn-ghost btn-sm" style="background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;" data-recid="\'+esc(s.phone_id)+\'" onclick="reconn(this.dataset.recid)">🔄 Reconnect</button><button class="btn btn-ghost btn-sm" style="background:#fef2f2;color:#dc2626;border-color:#fecaca;" data-discid="\'+esc(s.phone_id)+\'" onclick="disc(this.dataset.discid)">🔌 Disconnect</button>\');' +
        '    return "<tr>"' +
        '      +"<td><div class=\'nlabel\'><strong>"+esc(s.label)+"</strong><div style=\'display:flex;align-items:center;gap:4px;margin-top:1px\'><span>"+esc(s.phone_id)+"</span><button class=\'btn btn-ghost btn-sm\' style=\'padding:1px 5px;font-size:.65rem;flex-shrink:0;\' data-num=\'"+esc(s.phone_id)+"\' onclick=\'copyNum(this.dataset.num)\' title=\'Salin Nomor\'>📋</button></div><span style=\'font-size:.7rem;color:#9ca3af;margin-top:1px\'>📅 "+regDate+(connUptime?" &nbsp;⏱ Konek "+connUptime:"")+"</span></div></td>"' +
        '      +"<td><span class=\'badge "+bc+"\'><span class=\'bdot\'></span>"+bl+"</span></td>"' +
        '      +"<td><a href=\'#\' style=\'color:#0ea5e9;text-decoration:underline;cursor:pointer\' data-grpid=\'"+esc(s.phone_id)+"\'  onclick=\'event.preventDefault();openGrpList(this.dataset.grpid)\'>"+s.groups+" grup</a></td>"' +
        '      +"<td><div style=\'display:flex;align-items:center;gap:5px;\'><code style=\'font-size:.70rem;background:#f3f4f6;padding:2px 7px;border-radius:4px;border:1px solid #e5e7eb;font-family:monospace;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:middle;\'>"+( s.api_token?s.api_token.substring(0,16)+\'…\':\'—\')+"</code>"+(s.api_token?"<button class=\'btn btn-ghost btn-sm\' style=\'padding:2px 7px;flex-shrink:0;\' data-tok=\'"+esc(s.api_token)+"\' onclick=\'copyTok(this.dataset.tok)\' title=\'Salin Token\'>📋</button>":"")+"</div></td>"' +
        '      +"<td style=\'display:flex;gap:7px;flex-wrap:wrap;align-items:center;\'>"+qr' +
        '      +"<button class=\'btn btn-ghost btn-sm\' style=\'background:#fefce8;color:#854d0e;border-color:#fde68a;\' data-histid=\'"+esc(s.phone_id)+"\' onclick=\'openHistory(this.dataset.histid)\'>📜 History</button>"' +
        '      +"<button class=\'btn btn-ghost btn-sm\' style=\'background:#f0fdf4;color:#065f46;border-color:#bbf7d0;\' onclick=\'openWebhook(\\""+esc(s.phone_id)+"\\",\\""+esc(s.webhook_url||"")+"\\",\\""+s.webhook_enabled+"\\")\'>🔗 Webhook</button>"' +
        '      +"<button class=\'btn btn-ghost btn-sm\' style=\'background:#ede9fe;color:#6d28d9;border-color:#c4b5fd;\' data-aiid=\'"+esc(s.phone_id)+"\' onclick=\'copyAI(this.dataset.aiid)\'>🤖 AI</button>"' +
        '      +(s.status==="open"?"<button class=\'btn btn-ghost btn-sm\' style=\'background:#ecfdf5;color:#047857;border-color:#a7f3d0;\' data-testid=\'"+esc(s.phone_id)+"\' onclick=\'openTestSend(this.dataset.testid)\'>📤 Test Kirim</button>":"")' +
        '      +"<button class=\'btn btn-ghost btn-sm\' style=\'background:#f0f9ff;color:#0369a1;border-color:#bae6fd;\' data-healthid=\'"+esc(s.phone_id)+"\' onclick=\'openHealth(this.dataset.healthid)\'>🩺 Health</button>"' +
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
        'async function reconn(id){' +
        '  try{' +
        '    const r=await fetch("/web/session/"+encodeURIComponent(id)+"/reconnect",{method:"POST",headers:{"Content-Type":"application/json"}});' +
        '    const d=await r.json();' +
        '    if(d.success){toast("Reconnect "+id+" dimulai...");setTimeout(refresh,3000);}else toast(d.error||"Gagal reconnect","err");' +
        '  }catch(e){toast("Reconnect diproses...");setTimeout(refresh,3000);}' +
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
        'function loadSrvInfo(){fetch("/web/healthcheck").then(r=>r.json()).then(d=>{const el=document.getElementById("srv-info");if(!el)return;' +
        'function bar(pct,color){return \'<div style="background:#e5e7eb;border-radius:4px;height:8px;margin-top:4px;overflow:hidden"><div style="height:100%;border-radius:4px;background:\'+color+\';width:\'+pct+\'%"></div></div>\';}' +
        'function pctColor(p){return p>90?"#dc2626":p>70?"#f59e0b":"#059669";}' +
        'const mp=parseFloat(d.memPercent);const dp=parseFloat(d.diskPercent);' +
        'const la1=parseFloat(d.loadAvg[0]);const lColor=la1>d.cpuCores?"#dc2626":la1>d.cpuCores*0.7?"#f59e0b":"#059669";' +
        'const items=[' +
        '  {l:"Status",v:\'<span style="font-size:1.1rem">\'+(d.status==="ok"?\'✅ <span style="color:#059669">Healthy</span>\':\'❌ <span style="color:#dc2626">Down</span>\')+\'</span>\'},' +
        '  {l:"Uptime",v:\'<span style="font-size:1rem">\'+d.uptime+\'</span>\'},' +
        '  {l:"Hostname",v:d.hostname},' +
        '  {l:"OS",v:d.os},' +
        '  {l:"CPU",v:d.cpu+\'<br><span style="font-size:.75rem;color:#6b7280">\'+d.cpuCores+\' cores</span>\'},' +
        '  {l:"Load Average",v:\'<span style="color:\'+lColor+\';font-weight:700">\'+d.loadAvg[0]+\'</span> / \'+d.loadAvg[1]+\' / \'+d.loadAvg[2]+\'<br><span style="font-size:.7rem;color:#6b7280">1m / 5m / 15m</span>\'},' +
        '  {l:"RAM",v:\'<span style="color:\'+pctColor(mp)+\';font-weight:700">\'+d.memUsed+\'</span> / \'+d.memTotal+\' (\'+d.memPercent+\'%%)\'+ bar(mp,pctColor(mp))},' +
        '  {l:"Disk",v:\'<span style="color:\'+pctColor(dp)+\';font-weight:700">\'+d.diskUsed+\'</span> / \'+d.diskTotal+\' (\'+d.diskPercent+\'%%)\'+ bar(dp,pctColor(dp))},' +
        '  {l:"Process Memory",v:\'RSS: \'+d.procRSS+\' &middot; Heap: \'+d.procHeap},' +
        '  {l:"Node.js",v:d.nodeVersion},' +
        '  {l:"App Version",v:\'<span style="color:#0ea5e9;font-weight:600">v\'+d.appVersion+\'</span>\'},' +
        '  {l:"Sessions",v:\'<span style="font-size:1.1rem;font-weight:700;color:#059669">\'+d.sessionsOpen+\'</span> online / \'+d.sessions+\' total\'}' +
        '];' +
        'el.innerHTML=items.map(i=>"<div style=\'background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px\'><div style=\'font-size:.7rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px\'>"+i.l+"</div><div style=\'font-size:.85rem;\'>"+i.v+"</div></div>").join("");' +
        '}).catch(()=>{const el=document.getElementById("srv-info");if(el)el.innerHTML="<div style=\'color:#dc2626\'>Gagal memuat info server</div>";});}' +
        'loadSrvInfo();setInterval(loadSrvInfo,15000);' +
        'async function srvAction(act){' +
        '  if(!confirm(act==="stop"?"⚠️ STOP service? Semua koneksi WA akan terputus dan dashboard tidak bisa diakses sampai service di-start manual.":"🔄 Restart service? Semua koneksi WA akan reconnect otomatis."))return;' +
        '  const res=document.getElementById("srv-action-result");' +
        '  res.innerHTML="<span style=\'color:#6b7280\'>⏳ Memproses...</span>";' +
        '  try{' +
        '    const r=await fetch("/web/service/"+act,{method:"POST"});' +
        '    const d=await r.json();' +
        '    if(d.success){res.innerHTML="<span style=\'color:#059669\'>✅ "+d.message+"</span>";if(act==="restart"){res.innerHTML+="<br><span style=\'color:#6b7280;font-size:.8rem\'>⏳ Menunggu server kembali...</span>";var _ri=setInterval(()=>{fetch("/login",{method:"HEAD",redirect:"manual"}).then(r=>{if(r.ok||r.status===200||r.status===302){clearInterval(_ri);window.location.href="/dashboard";}}).catch(()=>{});},3000);}}' +
        '    else res.innerHTML="<span style=\'color:#dc2626\'>❌ "+d.error+"</span>";' +
        '  }catch(e){res.innerHTML="<span style=\'color:#dc2626\'>Koneksi terputus (service mungkin sudah "+act+")</span>";if(act==="restart"){res.innerHTML+="<br><span style=\'color:#6b7280;font-size:.8rem\'>⏳ Menunggu server kembali...</span>";var _ri2=setInterval(()=>{fetch("/login",{method:"HEAD",redirect:"manual"}).then(r=>{if(r.ok||r.status===200||r.status===302){clearInterval(_ri2);window.location.href="/dashboard";}}).catch(()=>{});},3000);}}' +
        '}' +
        'function copyTok(tok){navigator.clipboard.writeText(tok).then(()=>toast("Token disalin! \u2713")).catch(()=>{const el=document.createElement("textarea");el.value=tok;document.body.appendChild(el);el.select();document.execCommand("copy");document.body.removeChild(el);toast("Token disalin! \u2713");});}' +
        'function copyNum(num){navigator.clipboard.writeText(num).then(()=>toast("Nomor disalin! \u2713")).catch(()=>{const el=document.createElement("textarea");el.value=num;document.body.appendChild(el);el.select();document.execCommand("copy");document.body.removeChild(el);toast("Nomor disalin! \u2713");});}' +
        'async function copyAI(pid){try{const r=await fetch("/web/session/"+encodeURIComponent(pid)+"/ai-instructions");const d=await r.json();if(d.text){await navigator.clipboard.writeText(d.text);toast("\ud83e\udd16 Instruksi AI disalin! ("+(d.text.length)+" chars)");}else toast(d.error||"Gagal","err");}catch(e){toast("Error: "+e.message,"err");}}' +
        'let _tsId=null,_tsTok=null;' +
        'async function openTestSend(id){_tsId=id;_tsTok=null;document.getElementById("ts-modal-id").textContent=id;document.getElementById("ts-number").value="";document.getElementById("ts-message").value="";document.getElementById("ts-result").innerHTML="";document.getElementById("ts-modal").classList.add("open");document.getElementById("ts-number").focus();try{const r=await fetch("/web/session/"+encodeURIComponent(id)+"/get-token");const d=await r.json();if(d.token)_tsTok=d.token;}catch(e){console.error(e);}}' +
        'function closeTestSend(){document.getElementById("ts-modal").classList.remove("open");_tsId=null;_tsTok=null;}' +
        'async function openHealth(id){' +
        '  const m=document.getElementById("health-modal");const b=document.getElementById("health-body");' +
        '  document.getElementById("health-modal-id").textContent=id;' +
        '  b.innerHTML="<div style=\'text-align:center;padding:30px;color:#6b7280\'>⏳ Memuat health data...</div>";' +
        '  m.classList.add("open");' +
        '  try{' +
        '    const r=await fetch("/web/session/"+encodeURIComponent(id)+"/health");const d=await r.json();' +
        '    if(d.error){b.innerHTML="<div style=\'color:#dc2626;padding:20px\'>❌ "+d.error+"</div>";return;}' +
        '    const sc=d.status==="open"?"#059669":"#dc2626";const sl=d.status==="open"?"🟢 Online":"🔴 Offline";' +
        '    const wsc=d.waState==="CONNECTED"?"#059669":d.waState==="OPENING"?"#f59e0b":"#dc2626";' +
        '    const lm=d.lastMessageAt?new Date(d.lastMessageAt).toLocaleString("id-ID"):"—";' +
        '    const items=[' +
        '      {l:"Status Koneksi",v:\'<span style="color:\'+sc+\';font-weight:700;font-size:1.05rem">\'+sl+"</span>"},' +
        '      {l:"WA State",v:\'<span style="color:\'+wsc+\';font-weight:600">\'+d.waState+"</span>"},' +
        '      {l:"WhatsApp ID",v:d.wid||"—"},' +
        '      {l:"Nama Profil",v:d.pushname||"—"},' +
        '      {l:"Platform",v:d.platform||"—"},' +
        '      {l:"WWeb Version",v:d.wwebVersion||"—"},' +
        '      {l:"Grup Terdaftar",v:\'<span style="font-weight:700;color:#0ea5e9">\'+d.groups+"</span>"},' +
        '      {l:"Pesan Masuk",v:\'<span style="font-weight:700;color:#059669">\'+d.msgIn+"</span>"},' +
        '      {l:"Pesan Keluar",v:\'<span style="font-weight:700;color:#6366f1">\'+d.msgOut+"</span>"},' +
        '      {l:"Pesan Terakhir",v:lm},' +
        '      {l:"Fail Count",v:\'<span style="color:\'+(d.failCount>0?"#dc2626":"#059669")+\';font-weight:600">\'+d.failCount+"</span>"},' +
        '      {l:"Webhook",v:d.webhookEnabled?\'<span style="color:#059669">✅ Aktif</span>\':\'<span style="color:#6b7280">❌ Nonaktif</span>\'}' +
        '    ];' +
        '    b.innerHTML="<div style=\'display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px\'>"+items.map(i=>"<div style=\'background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px\'><div style=\'font-size:.7rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px\'>"+i.l+"</div><div style=\'font-size:.85rem\'>"+i.v+"</div></div>").join("")+"</div>"' +
        '    +(d.webhookUrl?"<div style=\'margin-top:12px;font-size:.75rem;color:#6b7280;word-break:break-all\'>Webhook URL: "+d.webhookUrl+"</div>":"");' +
        '  }catch(e){b.innerHTML="<div style=\'color:#dc2626;padding:20px\'>Error: "+e.message+"</div>";}' +
        '}' +
        'function closeHealth(){document.getElementById("health-modal").classList.remove("open");}' +
        'async function doTestSend(){' +
        '  const num=document.getElementById("ts-number").value.trim();' +
        '  const msg=document.getElementById("ts-message").value.trim();' +
        '  const res=document.getElementById("ts-result");' +
        '  if(!num||!msg){res.innerHTML="<div style=\'color:#dc2626\'>Nomor dan pesan wajib diisi!</div>";return;}' +
        '  res.innerHTML="<div style=\'color:#6b7280\'>⏳ Mengirim...</div>";' +
        '  try{' +
        '    const r=await fetch("/api/send",{method:"POST",headers:{"Content-Type":"application/json","Authorization":"Bearer "+_tsTok},body:JSON.stringify({phone_id:_tsId,number:num,message:msg})});' +
        '    const d=await r.json();' +
        '    if(d.success){res.innerHTML="<div style=\'color:#059669;font-weight:600\'>✅ Pesan terkirim!</div><pre style=\'font-size:.75rem;background:#f0fdf4;padding:8px;border-radius:6px;margin-top:6px;overflow:auto\'>"+JSON.stringify(d,null,2)+"</pre>";}' +
        '    else if(d.reconnecting){res.innerHTML="<div style=\'color:#f59e0b;font-weight:600\'>⚠️ "+d.error+"</div><button class=\'btn btn-primary btn-sm\' style=\'margin-top:8px\' onclick=\'setTimeout(doTestSend,0)\'>🔄 Coba Lagi</button>";}' +
        '    else{res.innerHTML="<div style=\'color:#dc2626;font-weight:600\'>❌ Gagal: "+(d.error||"Unknown")+"</div>";}' +
        '  }catch(e){res.innerHTML="<div style=\'color:#dc2626\'>Error: "+e.message+"</div>";}' +
        '}' +
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
        'document.addEventListener("click",function(e){if(e.target&&e.target.id==="qr-modal")closeQRModal();if(e.target&&e.target.id==="add-modal")closeAddModal();if(e.target&&e.target.id==="wh-modal")closeWebhook();if(e.target&&e.target.id==="grp-modal")closeGrpList();if(e.target&&e.target.id==="hist-modal")closeHistory();if(e.target&&e.target.id==="ts-modal")closeTestSend();if(e.target&&e.target.id==="health-modal")closeHealth();});' +
        'function openGrpList(pid){document.getElementById("grp-modal-id").textContent=pid;document.getElementById("grp-body").innerHTML="";document.getElementById("grp-modal").classList.add("open");document.getElementById("grp-create-form").style.display="none";loadGrpList(pid);}' +
        'function closeGrpList(){document.getElementById("grp-modal").classList.remove("open");}' +
        'function toggleCreateGrp(){var f=document.getElementById("grp-create-form");f.style.display=f.style.display==="none"?"block":"none";}' +
        'async function doCreateGrp(){' +
        '  var pid=document.getElementById("grp-modal-id").textContent;' +
        '  var nm=document.getElementById("grp-new-name").value.trim();' +
        '  var pp=document.getElementById("grp-new-members").value.trim();' +
        '  if(!nm||!pp){alert("Nama grup dan minimal 1 peserta harus diisi");return;}' +
        '  var btn=document.getElementById("grp-create-btn");btn.disabled=true;btn.textContent="Membuat...";' +
        '  try{var r=await fetch("/web/session/"+encodeURIComponent(pid)+"/create-group",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({name:nm,participants:pp})});' +
        '    var d=await r.json();if(!r.ok)throw new Error(d.error||"Gagal");' +
        '    alert("Grup \\""+nm+"\\" berhasil dibuat!");document.getElementById("grp-new-name").value="";document.getElementById("grp-new-members").value="";' +
        '    document.getElementById("grp-create-form").style.display="none";loadGrpList(pid);' +
        '  }catch(e){alert("Error: "+e.message);}finally{btn.disabled=false;btn.textContent="Buat Grup";}' +
        '}' +
        'async function leaveGrp(pid,jid,name){' +
        '  if(!confirm("Yakin ingin keluar dari grup \\""+name+"\\"?"))return;' +
        '  try{var r=await fetch("/web/session/"+encodeURIComponent(pid)+"/leave-group",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({group_jid:jid})});' +
        '    var d=await r.json();if(!r.ok)throw new Error(d.error||"Gagal");loadGrpList(pid);' +
        '  }catch(e){alert("Error: "+e.message);}' +
        '}' +
        'async function loadGrpList(pid){' +
        '  const tb=document.getElementById("grp-body");tb.innerHTML=\'<tr><td colspan="4" style="text-align:center;padding:20px"><span class="spin" style="display:inline-block;margin-right:8px"></span>Memuat...</td></tr>\';' +
        '  try{const r=await fetch("/web/session/"+encodeURIComponent(pid)+"/groups");const d=await r.json();' +
        '    if(!d.groups?.length){tb.innerHTML=\'<tr><td colspan="4" style="text-align:center;padding:20px;color:#9ca3af">Tidak ada grup.</td></tr>\';return;}' +
        '    tb.innerHTML=d.groups.map(g=>{' +
        '      return "<tr><td><strong>"+esc(g.name)+"</strong><div style=\'font-size:.7rem;font-family:monospace;color:#9ca3af;margin-top:2px\'>"+esc(g.jid)+"</div></td><td>"+g.participants+" anggota</td>' +
        '        <td style=\'white-space:nowrap\'><button class=\'btn btn-ghost btn-sm\' style=\'font-size:.75rem\' data-cpjid=\'"+esc(g.jid)+"\' onclick=\'navigator.clipboard.writeText(this.dataset.cpjid);this.textContent=\\"\u2705\\";setTimeout(()=>{this.textContent=\\"\ud83d\udccb JID\\"},1000)\'>\ud83d\udccb JID</button> <button class=\'btn btn-ghost btn-sm\' style=\'font-size:.75rem;color:#ef4444\' data-leavejid=\'"+esc(g.jid)+"\' data-leavename=\'"+esc(g.name.replace(/\'/g,""))+"\' data-leavepid=\'"+esc(pid)+"\' onclick=\'leaveGrp(this.dataset.leavepid,this.dataset.leavejid,this.dataset.leavename)\'>🚪 Keluar</button></td></tr>";' +
        '    }).join("");' +
        '  }catch(e){tb.innerHTML=\'<tr><td colspan="4" style="text-align:center;color:#ef4444">Error: \'+e.message+\'</td></tr>\';}' +
        '}' +
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

    const grpModal =
        '<div class="modal-overlay" id="grp-modal">' +
        '<div class="modal-box" style="text-align:left;max-width:700px;max-height:85vh;display:flex;flex-direction:column;">' +
        '<button class="modal-close" onclick="closeGrpList()" title="Tutup">\u2715</button>' +
        '<div style="display:flex;align-items:center;gap:10px;margin-bottom:4px"><div style="font-size:1.1rem;font-weight:700">\ud83d\udc65 Daftar Grup</div><button class="btn btn-primary btn-sm" style="font-size:.75rem" onclick="toggleCreateGrp()">➕ Buat Grup</button></div>' +
        '<div style="font-size:.8rem;color:#6b7280;margin-bottom:12px" id="grp-modal-id"></div>' +
        '<div id="grp-create-form" style="display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-bottom:12px">' +
        '<div style="font-weight:600;font-size:.85rem;margin-bottom:8px">Buat Grup Baru</div>' +
        '<input id="grp-new-name" placeholder="Nama grup" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:.85rem;margin-bottom:8px;box-sizing:border-box">' +
        '<input id="grp-new-members" placeholder="Nomor peserta (pisahkan koma, cth: 6281xxx,6282xxx)" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:.85rem;margin-bottom:8px;box-sizing:border-box">' +
        '<button id="grp-create-btn" class="btn btn-primary btn-sm" onclick="doCreateGrp()">Buat Grup</button>' +
        '</div>' +
        '<div style="overflow:auto;flex:1;"><table style="width:100%"><thead><tr>' +
        '<th>Nama Grup / JID</th><th>Anggota</th><th>Aksi</th>' +
        '</tr></thead><tbody id="grp-body"></tbody></table></div>' +
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

    const testSendModal =
        '<div class="modal-overlay" id="ts-modal">' +
        '<div class="modal-box" style="text-align:left;max-width:460px">' +
        '<button class="modal-close" onclick="closeTestSend()" title="Tutup">✕</button>' +
        '<div style="font-size:1.1rem;font-weight:700;margin-bottom:4px">📤 Test Kirim Pesan</div>' +
        '<div style="font-size:.8rem;color:#6b7280;margin-bottom:16px">Session: <strong id="ts-modal-id"></strong></div>' +
        '<div class="form-group"><label>Nomor Tujuan</label>' +
        '<input class="input" id="ts-number" placeholder="628xxxxxxxxxx atau 08xxxxxxxxxx" autocomplete="off"></div>' +
        '<div class="form-group"><label>Pesan</label>' +
        '<textarea class="input" id="ts-message" rows="3" placeholder="Ketik pesan test..."></textarea></div>' +
        '<div style="display:flex;gap:8px;justify-content:flex-end">' +
        '<button class="btn btn-ghost" onclick="closeTestSend()">Batal</button>' +
        '<button class="btn btn-primary" onclick="doTestSend()">📤 Kirim</button>' +
        '</div>' +
        '<div id="ts-result" style="margin-top:12px;font-size:.85rem"></div>' +
        '</div></div>';

    const healthModal =
        '<div class="modal-overlay" id="health-modal">' +
        '<div class="modal-box" style="text-align:left;max-width:640px">' +
        '<button class="modal-close" onclick="closeHealth()" title="Tutup">✕</button>' +
        '<div style="font-size:1.1rem;font-weight:700;margin-bottom:4px">🩺 Health Check Session</div>' +
        '<div style="font-size:.8rem;color:#6b7280;margin-bottom:16px">Session: <strong id="health-modal-id"></strong></div>' +
        '<div id="health-body"></div>' +
        '</div></div>';

    res.send(layout('Dashboard', 'Kelola nomor WhatsApp Anda', stats + serverPanel + table + modal + grpModal + histModal + testSendModal + healthModal + js, 'device'));
});

// ─── WEB API ──────────────────────────────────────────────────────────────────
app.get('/web/healthcheck', requireLogin, (req, res) => {
    const upSec = process.uptime();
    const days = Math.floor(upSec / 86400);
    const hrs = Math.floor((upSec % 86400) / 3600);
    const mins = Math.floor((upSec % 3600) / 60);
    const uptime = (days > 0 ? days + 'd ' : '') + hrs + 'j ' + mins + 'm';
    const totalMem = os.totalmem();
    const freeMem = os.freemem();
    const usedMem = totalMem - freeMem;
    const memPercent = ((usedMem / totalMem) * 100).toFixed(1);
    const fmtMB = (b) => (b / 1024 / 1024).toFixed(0) + ' MB';
    const cpus = os.cpus();
    const cpuModel = cpus.length > 0 ? cpus[0].model.trim() : 'N/A';
    const loadAvg = os.loadavg();
    // Disk usage via sync
    let diskTotal = '-', diskUsed = '-', diskFree = '-', diskPercent = '0';
    try {
        const df = execSync('df -BM / | tail -1', { timeout: 3000, encoding: 'utf8' }).trim().split(/\s+/);
        if (df.length >= 5) { diskTotal = df[1]; diskUsed = df[2]; diskFree = df[3]; diskPercent = df[4].replace('%', ''); }
    } catch { }
    // Process memory
    const procMem = process.memoryUsage();
    const procRSS = (procMem.rss / 1024 / 1024).toFixed(1) + ' MB';
    const procHeap = (procMem.heapUsed / 1024 / 1024).toFixed(1) + ' MB';
    res.json({
        status: 'ok',
        uptime,
        uptimeSec: Math.floor(upSec),
        cpu: cpuModel,
        cpuCores: cpus.length,
        loadAvg: loadAvg.map(l => l.toFixed(2)),
        memTotal: fmtMB(totalMem),
        memUsed: fmtMB(usedMem),
        memFree: fmtMB(freeMem),
        memPercent,
        diskTotal, diskUsed, diskFree, diskPercent,
        procRSS, procHeap,
        nodeVersion: process.version,
        os: os.type() + ' ' + os.release(),
        hostname: os.hostname(),
        appVersion: APP_VERSION,
        sessions: sessions.size,
        sessionsOpen: Array.from(sessions.values()).filter(s => s.status === 'open').length,
    });
});

let _shuttingDown = false;
async function gracefulShutdown() {
    _shuttingDown = true;
    console.log('[Gateway] Shutting down gracefully, destroying all sessions...');
    const withTimeout = (p, ms) => Promise.race([p, new Promise(r => setTimeout(r, ms))]);
    const destroyPromises = [];
    for (const [id, sess] of sessions) {
        if (sess.client) {
            destroyPromises.push(
                withTimeout(sess.client.destroy().catch(() => { }), 10000).then(() => { console.log('[Gateway] Destroyed ' + id); })
            );
        }
    }
    await withTimeout(Promise.allSettled(destroyPromises), 15000);
    // kill any remaining chrome processes owned by this app
    try { execSync('pkill -f "auth_info/session-" || true', { timeout: 5000 }); } catch { }
    console.log('[Gateway] All sessions destroyed.');
}
process.on('SIGTERM', async () => { await gracefulShutdown(); process.exit(0); });
process.on('SIGINT', async () => { await gracefulShutdown(); process.exit(0); });

app.post('/web/service/:action', requireLogin, async (req, res) => {
    const action = req.params.action;
    if (action === 'restart') {
        res.json({ success: true, message: 'Service akan restart dalam 2 detik...' });
        setTimeout(async () => { await gracefulShutdown(); process.exit(0); }, 2000);
    } else if (action === 'stop') {
        try {
            await gracefulShutdown();
            execSync('supervisorctl stop integrasi-wa', { timeout: 5000 });
            res.json({ success: true, message: 'Service dihentikan.' });
        } catch {
            res.json({ success: true, message: 'Service akan berhenti...' });
            setTimeout(() => { process.exit(1); }, 2000);
        }
    } else {
        res.status(400).json({ error: 'Action tidak valid. Gunakan: restart atau stop' });
    }
});

app.get('/web/sessions', requireLogin, (req, res) => {
    const list = Array.from(sessions.entries()).map(([id, s]) => {
        const hasPaired = hasValidAuth(id) || s.status === 'open';
        return {
            phone_id: id, label: s.label, status: s.status, groups: s.groupCache.size,
            api_token: s.apiToken || '', webhook_url: s.webhookUrl || '', webhook_enabled: s.webhookEnabled || false,
            created_at: s.createdAt ? new Date(s.createdAt).toISOString() : null,
            connected_at: s.connectedAt ? new Date(s.connectedAt).toISOString() : null,
            paired: hasPaired,
        };
    });
    res.json({ sessions: list });
});

app.get('/web/session/:id/groups', requireLogin, (req, res) => {
    const id = sanitizeId(req.params.id);
    const s = sessions.get(id);
    if (!s) return res.status(404).json({ error: 'Session not found' });
    const groups = [];
    for (const [jid, meta] of s.groupCache) groups.push({ jid, name: meta.subject, participants: meta.participants?.length || 0 });
    res.json({ phone_id: id, groups });
});

app.post('/web/session/:id/leave-group', requireLogin, async (req, res) => {
    const id = sanitizeId(req.params.id);
    const s = sessions.get(id);
    if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected' });
    const { group_jid } = req.body;
    if (!group_jid) return res.status(400).json({ error: 'group_jid required' });
    try {
        const chat = await s.client.getChatById(group_jid);
        await chat.leave();
        s.groupCache.delete(group_jid);
        res.json({ success: true });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});

app.post('/web/session/:id/create-group', requireLogin, async (req, res) => {
    const id = sanitizeId(req.params.id);
    const s = sessions.get(id);
    if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected' });
    const { name, participants } = req.body;
    if (!name) return res.status(400).json({ error: 'Group name required' });
    const nums = (participants || '').split(',').map(n => n.trim()).filter(Boolean).map(n => numberToJid(n));
    if (!nums.length) return res.status(400).json({ error: 'At least one participant required' });
    try {
        const result = await s.client.createGroup(name, nums);
        await refreshGroupCacheForSession(id);
        res.json({ success: true, data: { gid: result.gid?._serialized || '', title: result.title || name } });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});

app.get('/web/session/:id/get-token', requireLogin, async (req, res) => {
    const id = sanitizeId(req.params.id);
    const sess = sessions.get(id);
    if (!sess) return res.status(404).json({ error: 'Sesi tidak ditemukan' });
    return res.json({ token: sess.apiToken || '' });
});

app.get('/web/session/:id/health', requireLogin, async (req, res) => {
    const id = sanitizeId(req.params.id);
    const sess = sessions.get(id);
    if (!sess) return res.status(404).json({ error: 'Sesi tidak ditemukan' });
    const data = { phone_id: id, label: sess.label, status: sess.status, groups: sess.groupCache.size, failCount: sess._failCount || 0, webhookUrl: sess.webhookUrl || '', webhookEnabled: sess.webhookEnabled || false };
    try { const st = await sess.client.getState(); data.waState = st || 'UNKNOWN'; } catch { data.waState = sess.status === 'open' ? 'CONNECTED' : 'DISCONNECTED'; }
    try { const info = sess.client.info; if (info) { data.wid = info.wid ? (info.wid.user || info.wid._serialized || '') : ''; data.pushname = info.pushname || ''; data.platform = info.platform || ''; } } catch { }
    try { data.wwebVersion = await sess.client.getWWebVersion(); } catch { data.wwebVersion = ''; }
    try {
        const msgRes = await db.query('SELECT created_at FROM messages_log WHERE session_id=$1 ORDER BY created_at DESC LIMIT 1', [id]);
        data.lastMessageAt = msgRes.rows.length ? msgRes.rows[0].created_at : null;
        const cntRes = await db.query('SELECT direction, COUNT(*)::int as cnt FROM messages_log WHERE session_id=$1 GROUP BY direction', [id]);
        data.msgIn = 0; data.msgOut = 0;
        cntRes.rows.forEach(r => { if (r.direction === 'in') data.msgIn = r.cnt; else data.msgOut = r.cnt; });
    } catch { data.lastMessageAt = null; data.msgIn = 0; data.msgOut = 0; }
    res.json(data);
});

app.post('/web/session/add', requireLogin, async (req, res) => {
    try {
        const phoneId = sanitizeId(req.body.phoneId || '');
        const label = String(req.body.label || '').trim().substring(0, 80) || phoneId;
        if (!phoneId) return res.status(400).json({ error: 'phoneId wajib diisi' });
        if (sessions.has(phoneId)) return res.status(409).json({ error: 'Sesi sudah ada' });
        const apiToken = randomBytes(16).toString('hex');
        await db.query('INSERT INTO wa_sessions(phone_id,label,api_token) VALUES($1,$2,$3) ON CONFLICT(phone_id) DO UPDATE SET label=$2', [phoneId, label, apiToken]);
        sessions.set(phoneId, { client: null, qrCode: null, qrDataUrl: null, status: 'disconnected', groupCache: new Map(), label: label, apiToken: apiToken, webhookUrl: '', webhookEnabled: false, _reconnectTimer: null, createdAt: new Date(), connectedAt: null });
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
        sess._userDisconnecting = true;  // Flag: user-initiated, skip email notification
        if (sess.client) {
            const c = sess.client;
            sess.client = null;
            try { await Promise.race([c.logout(), new Promise(r => setTimeout(r, 10000))]); } catch { }
            try { await Promise.race([c.destroy(), new Promise(r => setTimeout(r, 5000))]); } catch { }
        }
        sess.status = 'disconnected';
        sess._userDisconnecting = false;
        sess.qrCode = null;
        sess.qrDataUrl = null;
        // Hapus auth files agar bisa pairing ulang
        try { fs.rmSync(path.join('./auth_info', 'session-' + id), { recursive: true, force: true }); } catch { }
        try { fs.rmSync(getSessionAuthDir(id), { recursive: true, force: true }); } catch { }
        console.log('[WA][' + id + '] Disconnected by user');
        res.json({ success: true });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

app.post('/web/session/:id/reconnect', requireLogin, async (req, res) => {
    try {
        const id = sanitizeId(req.params.id);
        const sess = sessions.get(id);
        if (!sess) return res.status(404).json({ error: 'Session not found' });
        // Force destroy existing client if stuck
        if (sess._reconnectTimer) { clearTimeout(sess._reconnectTimer); sess._reconnectTimer = null; }
        if (sess.client) {
            try { await Promise.race([sess.client.destroy(), new Promise(r => setTimeout(r, 5000))]); } catch { }
            sess.client = null;
        }
        sess.status = 'disconnected';
        sess.connectedAt = null;
        sess._connectingSince = null;
        sess._failCount = 0;
        console.log('[WA][' + id + '] Force reconnect by user');
        // Start fresh session (auth data preserved, no QR needed)
        startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled);
        res.json({ success: true });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

const _PRIVATE_IP_RE = /^(127\.|10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|0\.0\.0\.0|localhost|\[::1\])/i;
function isPrivateUrl(urlStr) {
    try {
        const u = new URL(urlStr);
        return _PRIVATE_IP_RE.test(u.hostname);
    } catch { return true; }
}
app.post('/web/session/:id/webhook', requireLogin, async (req, res) => {
    try {
        const id = sanitizeId(req.params.id);
        const webhookUrl = String(req.body.webhook_url || '').trim().substring(0, 500);
        const webhookEnabled = req.body.webhook_enabled === true || req.body.webhook_enabled === 'true';
        if (webhookUrl && (!/^https?:\/\//i.test(webhookUrl) || isPrivateUrl(webhookUrl)))
            return res.status(400).json({ ok: false, error: 'URL webhook tidak valid atau mengarah ke jaringan internal' });
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
    // Auth data preserved — just restart the session
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
        const puppeteerArgs = [
            '--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
            '--disable-gpu', '--no-first-run', '--no-zygote',
            '--disk-cache-size=1', '--media-cache-size=1',
            '--disable-extensions', '--disable-plugins', '--disable-sync', '--disable-translate',
            '--js-flags=--max-old-space-size=384', '--aggressive-cache-discard', '--disable-cache',
        ];
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
        // Auth data preserved — user can retry pairing
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
        'try{const r=await fetch("/web/session/"+encodeURIComponent(pid)+"/groups");const d=await r.json();' +
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
        '  try{const r=await fetch("/web/session/"+encodeURIComponent(pid)+"/groups");const d=await r.json();' +
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
            ? 'curl -X GET "' + fullUrl + (exampleBody ? '?' + Object.entries(exampleBody).map(([k, v]) => k + '=' + v).join('&') : '') + '" \\\n  -H "' + hdr + '"'
            : 'curl -X POST "' + fullUrl + '" \\\n  -H "' + hdr + '" \\\n  -H "Content-Type: application/json" \\\n  -d \'' + (exampleBody ? JSON.stringify(exampleBody) : '{}') + '\'';

        // Generate PHP CodeIgniter example
        let phpEx = '';
        if (method === 'GET') {
            const qp = exampleBody ? '?' + Object.entries(exampleBody).map(([k, v]) => k + '=' + v).join('&') : '';
            phpEx = '$client = \\Config\\Services::curlrequest();\n\n$response = $client->get(\'' + fullUrl + qp + '\', [\n    \'headers\' => [\n        \'Authorization\' => \'Bearer ' + token + '\'\n    ]\n]);\n\n$data = json_decode($response->getBody(), true);';
        } else {
            phpEx = '$client = \\Config\\Services::curlrequest();\n\n$response = $client->post(\'' + fullUrl + '\', [\n    \'headers\' => [\n        \'Authorization\' => \'Bearer ' + token + '\',\n        \'Content-Type\'  => \'application/json\'\n    ],\n    \'json\' => ' + (exampleBody ? JSON.stringify(exampleBody, null, 4).replace(/"/g, "'").replace(/\n/g, '\n    ') : '[]') + '\n]);\n\n$data = json_decode($response->getBody(), true);';
        }

        // Generate JavaScript (fetch) example
        let jsEx = '';
        if (method === 'GET') {
            const qp = exampleBody ? '?' + Object.entries(exampleBody).map(([k, v]) => k + '=' + encodeURIComponent(v)).join('&') : '';
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

        apiCard('Kirim Lokasi', 'Mengirim lokasi (latitude/longitude) ke nomor personal.', 'POST', '/api/send-location',
            [
                { name: 'number', type: 'string', desc: 'Nomor tujuan (628xxx)', required: true },
                { name: 'latitude', type: 'number', desc: 'Latitude lokasi (contoh: -6.2088)', required: true },
                { name: 'longitude', type: 'number', desc: 'Longitude lokasi (contoh: 106.8456)', required: true },
                { name: 'description', type: 'string', desc: 'Deskripsi/nama lokasi (opsional)', required: false },
                { name: 'phone_id', type: 'string', desc: 'Phone ID pengirim (opsional)', required: false },
            ],
            { phone_id: "6281317647379", number: "6281234567890", latitude: -6.2088, longitude: 106.8456, description: "Monas, Jakarta" },
            { success: true, phone_id: "6281317647379", data: { id: "3EB0xxxx" } }, null) +

        apiCard('Kirim Pesan + Tombol', 'Mengirim pesan dengan reply buttons (maks 3 tombol). ⚠️ Buttons mungkin tidak tampil di semua versi WA.', 'POST', '/api/send-buttons',
            [
                { name: 'number', type: 'string', desc: 'Nomor tujuan (628xxx)', required: true },
                { name: 'message', type: 'string', desc: 'Isi pesan utama', required: true },
                { name: 'buttons', type: 'array', desc: 'Array tombol [{id:"btn1",text:"Ya"},{id:"btn2",text:"Tidak"}] maks 3', required: true },
                { name: 'footer', type: 'string', desc: 'Teks footer kecil di bawah (opsional)', required: false },
                { name: 'phone_id', type: 'string', desc: 'Phone ID pengirim (opsional)', required: false },
            ],
            { phone_id: "6281317647379", number: "6281234567890", message: "Apakah Anda setuju?", footer: "Pilih salah satu", buttons: [{id:"btn1",text:"Ya, Setuju"},{id:"btn2",text:"Tidak"},{id:"btn3",text:"Nanti"}] },
            { success: true, phone_id: "6281317647379", data: { id: "3EB0xxxx" } },
            'Maksimal 3 tombol. Setiap tombol butuh id (unik) dan text (label).') +

        apiCard('Kirim Pesan + List Menu', 'Mengirim pesan dengan list/menu pilihan (tombol yang buka daftar). ⚠️ List mungkin tidak tampil di semua versi WA.', 'POST', '/api/send-list',
            [
                { name: 'number', type: 'string', desc: 'Nomor tujuan (628xxx)', required: true },
                { name: 'message', type: 'string', desc: 'Isi pesan utama', required: true },
                { name: 'buttonText', type: 'string', desc: 'Label tombol menu (contoh: "Lihat Menu")', required: true },
                { name: 'sections', type: 'array', desc: 'Array section [{title:"Kategori",rows:[{id:"1",title:"Opsi A",description:"Detail"}]}]', required: true },
                { name: 'title', type: 'string', desc: 'Judul list (opsional)', required: false },
                { name: 'footer', type: 'string', desc: 'Teks footer (opsional)', required: false },
                { name: 'phone_id', type: 'string', desc: 'Phone ID pengirim (opsional)', required: false },
            ],
            { phone_id: "6281317647379", number: "6281234567890", message: "Silakan pilih layanan:", buttonText: "Lihat Menu", title: "Menu Layanan", footer: "Ketik /help untuk bantuan", sections: [{title:"Layanan",rows:[{id:"1",title:"Konsultasi",description:"Konsultasi gratis"},{id:"2",title:"Order",description:"Pesan produk"}]}] },
            { success: true, phone_id: "6281317647379", data: { id: "3EB0xxxx" } },
            'Setiap section punya title dan rows[]. Setiap row butuh id, title, dan opsional description.') +

        apiCard('Kirim Polling', 'Mengirim polling/voting ke nomor personal.', 'POST', '/api/send-poll',
            [
                { name: 'number', type: 'string', desc: 'Nomor tujuan (628xxx)', required: true },
                { name: 'question', type: 'string', desc: 'Pertanyaan polling', required: true },
                { name: 'options', type: 'array', desc: 'Array pilihan ["Opsi A","Opsi B","Opsi C"] min 2, maks 12', required: true },
                { name: 'allowMultiple', type: 'boolean', desc: 'Boleh pilih lebih dari satu? (default: true)', required: false },
                { name: 'phone_id', type: 'string', desc: 'Phone ID pengirim (opsional)', required: false },
            ],
            { phone_id: "6281317647379", number: "6281234567890", question: "Mau makan apa?", options: ["Nasi Goreng","Mie Ayam","Bakso"], allowMultiple: false },
            { success: true, phone_id: "6281317647379", data: { id: "3EB0xxxx" } },
            'Minimal 2 opsi, maksimal 12. allowMultiple=false untuk single choice.') +

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

        apiCard('Leave Group', 'Keluar dari grup WhatsApp. Session akan otomatis keluar dan grup dihapus dari cache.', 'POST', '/api/leave-group',
            [
                { name: 'group_id', type: 'string', desc: 'JID atau nama grup', required: true },
                { name: 'phone_id', type: 'string', desc: 'Phone ID (opsional)', required: false },
            ],
            { phone_id: "6281317647379", group_id: "120363xxx@g.us" },
            { success: true, phone_id: "6281317647379", left_group: "120363xxx@g.us" },
            '⚠️ Aksi ini tidak bisa di-undo. Anda harus diundang ulang untuk join kembali.') +

        apiCard('Buat Grup Baru', 'Membuat grup WhatsApp baru dengan nama dan daftar peserta.', 'POST', '/api/create-group',
            [
                { name: 'name', type: 'string', desc: 'Nama grup baru', required: true },
                { name: 'participants', type: 'string|array', desc: 'Daftar nomor peserta (comma-separated atau array)', required: true },
                { name: 'phone_id', type: 'string', desc: 'Phone ID (opsional)', required: false },
            ],
            { phone_id: "6281317647379", name: "Grup Project ABC", participants: "6281234567890,6289876543210" },
            { success: true, phone_id: "6281317647379", data: { gid: "120363xxx@g.us", title: "Grup Project ABC" } },
            'Peserta harus sudah menyimpan nomor WA ini, atau izinkan undangan dari semua orang di pengaturan privasi.') +

        '<div style="font-size:1.1rem;font-weight:700;margin:24px 0 14px;padding-bottom:8px;border-bottom:2px solid #8b5cf6;color:#5b21b6">🔔 Webhook Payload</div>' +
        '<div class="card" style="margin-bottom:18px">' +
        '<div class="card-header"><span class="card-title" style="font-size:.95rem">Format Webhook Incoming Message</span></div>' +
        '<div class="card-body">' +
        '<p style="color:#6b7280;font-size:.85rem;margin-bottom:12px">Setiap pesan masuk akan di-POST ke webhook URL yang dikonfigurasi per session. Payload JSON:</p>' +
        '<div style="margin-bottom:12px"><div style="font-weight:600;font-size:.85rem;margin-bottom:6px">Fields</div>' +
        '<table style="width:100%;border-collapse:collapse;font-size:.85rem"><thead><tr style="background:#f8fafc;text-align:left"><th style="padding:8px 10px;font-weight:600">Field</th><th style="padding:8px 10px;font-weight:600">Tipe</th><th style="padding:8px 10px;font-weight:600">Keterangan</th></tr></thead><tbody>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">phone_id</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">string</td><td style="padding:6px 10px;font-size:.82rem">Phone ID penerima (session)</td></tr>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">message_id</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">string</td><td style="padding:6px 10px;font-size:.82rem">ID unik pesan WhatsApp</td></tr>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">message</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">string</td><td style="padding:6px 10px;font-size:.82rem">Isi teks pesan</td></tr>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">type</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">string</td><td style="padding:6px 10px;font-size:.82rem">Tipe pesan: conversation, image, video, audio, document, sticker, location, contact</td></tr>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">timestamp</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">number</td><td style="padding:6px 10px;font-size:.82rem">Unix timestamp (detik)</td></tr>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">sender</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">string</td><td style="padding:6px 10px;font-size:.82rem">Nomor pengirim (628xxx)</td></tr>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">sender_name</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">string|null</td><td style="padding:6px 10px;font-size:.82rem">Push name pengirim</td></tr>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">from</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">string</td><td style="padding:6px 10px;font-size:.82rem">JID pengirim tanpa suffix</td></tr>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">location</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">object|undefined</td><td style="padding:6px 10px;font-size:.82rem">Hanya ada jika type=location: {latitude, longitude, description, url}</td></tr>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">group_id</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">string|undefined</td><td style="padding:6px 10px;font-size:.82rem">Hanya ada jika dari grup: JID grup (xxx@g.us)</td></tr>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">group_name</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">string|undefined</td><td style="padding:6px 10px;font-size:.82rem">Nama grup (jika dari grup)</td></tr>' +
        '<tr><td style="padding:6px 10px;font-family:monospace;font-size:.82rem;font-weight:600;color:#065f46">_key</td><td style="padding:6px 10px;font-size:.82rem;color:#6b7280">object</td><td style="padding:6px 10px;font-size:.82rem">{remoteJid, id, fromMe, participant} — bisa dipakai untuk delete/reply</td></tr>' +
        '</tbody></table></div>' +
        '<div style="margin-bottom:8px"><div style="font-weight:600;font-size:.85rem;margin-bottom:6px">Contoh Payload (Pesan Teks)</div>' +
        '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;font-family:monospace;font-size:.78rem;white-space:pre-wrap;word-break:break-all">' + escHtml(JSON.stringify({ phone_id: '6281317647379', message_id: '3EB0xxxx', message: 'Halo, apa kabar?', type: 'conversation', timestamp: 1741392000, sender: '6281234567890', sender_name: 'John', from: '6281234567890', pushname: 'John', _key: { remoteJid: '6281234567890@c.us', id: '3EB0xxxx', fromMe: false } }, null, 2)) + '</div></div>' +
        '<div style="margin-bottom:8px"><div style="font-weight:600;font-size:.85rem;margin-bottom:6px">Contoh Payload (Lokasi)</div>' +
        '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-family:monospace;font-size:.78rem;white-space:pre-wrap;word-break:break-all">' + escHtml(JSON.stringify({ phone_id: '6281317647379', message_id: '3EB0xxxx', message: '', type: 'location', timestamp: 1741392000, sender: '6281234567890', sender_name: 'John', from: '6281234567890', pushname: 'John', location: { latitude: -6.2088, longitude: 106.8456, description: 'Monas, Jakarta', url: 'https://maps.google.com/...' }, _key: { remoteJid: '6281234567890@c.us', id: '3EB0xxxx', fromMe: false } }, null, 2)) + '</div></div>' +
        '<div style="margin-bottom:8px"><div style="font-weight:600;font-size:.85rem;margin-bottom:6px">Contoh Payload (Pesan Grup)</div>' +
        '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;font-family:monospace;font-size:.78rem;white-space:pre-wrap;word-break:break-all">' + escHtml(JSON.stringify({ phone_id: '6281317647379', message_id: '3EB0xxxx', message: 'Pengumuman!', type: 'conversation', timestamp: 1741392000, sender: '6281234567890', sender_name: 'John', from: '120363xxx', pushname: 'John', group_id: '120363xxx@g.us', from_group: '120363xxx@g.us', group_name: 'Grup Kerja', _key: { remoteJid: '120363xxx@g.us', id: '3EB0xxxx', fromMe: false, participant: '6281234567890@c.us' } }, null, 2)) + '</div></div>' +
        '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;margin-top:8px;font-size:.8rem;color:#92400e">💡 Webhook dapat dikonfigurasi masing-masing per session melalui tombol 🔗 Webhook di dashboard.</div>' +
        '</div></div>' +

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

// ─── PAGE: RELEASE NOTES ──────────────────────────────────────────────────────
app.get('/release-notes', requireLogin, (req, res) => {
    const body =
        '<div style="max-width:800px">' +
        '<div class="card" style="margin-bottom:20px;background:linear-gradient(135deg,#1e1b4b,#312e81);color:#fff">' +
        '<div class="card-body" style="padding:24px 28px">' +
        '<div style="font-size:1.3rem;font-weight:700;margin-bottom:6px">📋 Release Notes</div>' +
        '<div style="font-size:.9rem;color:#c7d2fe;margin-bottom:8px">Riwayat perubahan Integrasi WA</div>' +
        '<div style="background:rgba(0,0,0,.25);border-radius:8px;padding:10px 16px;font-family:monospace;font-size:.85rem;color:#e0e7ff">Versi saat ini: <strong>v' + APP_VERSION + '</strong></div>' +
        '</div></div>' +

        '<div class="card" style="margin-bottom:16px;border-left:4px solid #10b981">' +
        '<div class="card-header" style="padding-bottom:4px"><span class="card-title" style="font-size:1rem">v2026.03-11</span>' +
        '<span style="font-size:.75rem;color:#6b7280;margin-left:10px">11 Maret 2026</span>' +
        '<span style="font-size:.7rem;font-weight:700;background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:4px;margin-left:8px">Latest</span></div>' +
        '<div class="card-body" style="padding-top:4px">' +
        '<div style="font-weight:600;font-size:.85rem;color:#065f46;margin-bottom:6px">✨ Fitur Baru</div>' +
        '<ul style="padding-left:20px;font-size:.85rem;line-height:1.8;margin:0 0 12px">' +
        '<li><strong>Kirim Pesan + Tombol (Buttons)</strong> — Endpoint <code>POST /api/send-buttons</code>. Kirim pesan interaktif dengan reply buttons (maks 3 tombol) langsung ke nomor personal.</li>' +
        '<li><strong>Kirim List Menu</strong> — Endpoint <code>POST /api/send-list</code>. Kirim pesan dengan daftar pilihan menu bertingkat (sections + rows) yang bisa dipilih penerima.</li>' +
        '<li><strong>Kirim Polling</strong> — Endpoint <code>POST /api/send-poll</code>. Buat polling/voting dengan min 2 dan maks 12 pilihan. Support single/multi choice.</li>' +
        '</ul>' +
        '<div style="font-weight:600;font-size:.85rem;color:#0369a1;margin-bottom:6px">⚡ Perbaikan Stabilitas</div>' +
        '<ul style="padding-left:20px;font-size:.85rem;line-height:1.8;margin:0 0 12px">' +
        '<li><strong>Heartbeat lebih toleran</strong> — Timeout ping dinaikkan dari 8 detik ke 20 detik. Perlu 2 kali gagal berturut-turut (±4 menit) baru reconnect. Mencegah false reconnect saat Chrome lambat karena memory pressure.</li>' +
        '<li><strong>Error Chrome crash lebih informatif</strong> — API mengembalikan pesan &quot;Sesi terputus, sedang reconnect&hellip;&quot; + tombol Coba Lagi di modal Test Kirim saat Chrome crash terdeteksi.</li>' +
        '</ul>' +
        '</div></div>' +

        '<div class="card" style="margin-bottom:16px;border-left:4px solid #6b7280">' +
        '<div class="card-header" style="padding-bottom:4px"><span class="card-title" style="font-size:1rem">v2026.03-10</span>' +
        '<span style="font-size:.75rem;color:#6b7280;margin-left:10px">10 Maret 2026</span>' +
        '</div>' +
        '<div class="card-body" style="padding-top:4px">' +
        '<div style="font-weight:600;font-size:.85rem;color:#065f46;margin-bottom:6px">✨ Fitur Baru</div>' +
        '<ul style="padding-left:20px;font-size:.85rem;line-height:1.8;margin:0 0 12px">' +
        '<li><strong>Leave Group (Keluar Grup)</strong> — Tombol 🚪 Keluar di daftar grup dashboard + endpoint <code>POST /api/leave-group</code>. Keluar dari grup WhatsApp langsung dari dashboard atau via API.</li>' +
        '<li><strong>Create Group (Buat Grup)</strong> — Tombol ➕ Buat Grup di modal daftar grup + endpoint <code>POST /api/create-group</code>. Buat grup baru dengan nama dan daftar peserta langsung dari dashboard atau via API.</li>' +
        '</ul>' +
        '<div style="font-weight:600;font-size:.85rem;color:#0369a1;margin-bottom:6px">🔒 Perbaikan Stabilitas</div>' +
        '<ul style="padding-left:20px;font-size:.85rem;line-height:1.8;margin:0 0 12px">' +
        '<li><strong>Proteksi Auth Data</strong> — Auth data sesi tidak lagi dihapus otomatis saat error/crash. Hanya aksi user (disconnect/delete/re-pair) yang menghapus auth.</li>' +
        '<li><strong>Auto-Reconnect Lebih Handal</strong> — Pattern crash yang lebih luas (frame detached, timeout, execution context destroyed) dikenali dan ditangani dengan benar.</li>' +
        '</ul>' +
        '</div></div>' +

        '<div class="card" style="margin-bottom:16px;border-left:4px solid #6b7280">' +
        '<div class="card-header" style="padding-bottom:4px"><span class="card-title" style="font-size:1rem">v2026.03-09</span>' +
        '<span style="font-size:.75rem;color:#6b7280;margin-left:10px">9 Maret 2026</span>' +
        '</div>' +
        '<div class="card-body" style="padding-top:4px">' +
        '<div style="font-weight:600;font-size:.85rem;color:#065f46;margin-bottom:6px">✨ Fitur Baru</div>' +
        '<ul style="padding-left:20px;font-size:.85rem;line-height:1.8;margin:0 0 12px">' +
        '<li><strong>Notifikasi Disconnect Otomatis</strong> — Ketika sesi WA terputus, sistem otomatis mengirim notifikasi via <strong>WhatsApp</strong> dan <strong>Email</strong> ke admin. Dilengkapi debounce 60 detik untuk mencegah notifikasi palsu saat auto-reconnect.</li>' +
        '<li><strong>Tanggal Register &amp; Uptime Koneksi</strong> — Setiap nomor WA di dashboard menampilkan tanggal pertama kali didaftarkan (📅) dan sudah berapa lama terkoneksi (⏱) sejak terakhir online.</li>' +
        '<li><strong>Loading Overlay Kode Pairing</strong> — Saat request kode pairing, tampil overlay progress bar animasi 60 detik. Kode yang berhasil ditampilkan dalam modal yang jelas dan besar.</li>' +
        '<li><strong>Version Fetch via fetchLatestWaWebVersion</strong> — Versi WA Web sekarang diambil langsung dari server WA (bukan sw.js yang sudah deprecated), fallback ke Baileys CDN jika gagal.</li>' +
        '</ul>' +
        '<div style="font-weight:600;font-size:.85rem;color:#dc2626;margin-bottom:6px">🐛 Perbaikan</div>' +
        '<ul style="padding-left:20px;font-size:.85rem;line-height:1.8;margin:0 0 12px">' +
        '<li>Fix syntax error pada tombol Tutup di modal kode pairing (nested quotes).</li>' +
        '<li>Fix pesan error 408 pairing code lebih informatif: <em>"WhatsApp menolak koneksi, tunggu 5-10 menit"</em>.</li>' +
        '<li>Browser fingerprint pairing diganti ke <code>[\'Chrome (Linux)\', \'\', \'\']</code> untuk kompatibilitas WA terbaru.</li>' +
        '</ul>' +
        '</div></div>' +

        '<div class="card" style="margin-bottom:16px;border-left:4px solid #6b7280">' +
        '<div class="card-header" style="padding-bottom:4px"><span class="card-title" style="font-size:1rem">v2026.03-08</span>' +
        '<span style="font-size:.75rem;color:#6b7280;margin-left:10px">8 Maret 2026</span></div>' +
        '<div class="card-body" style="padding-top:4px">' +
        '<div style="font-weight:600;font-size:.85rem;color:#065f46;margin-bottom:6px">✨ Fitur Baru</div>' +
        '<ul style="padding-left:20px;font-size:.85rem;line-height:1.8;margin:0 0 12px">' +
        '<li><strong>Daftar Grup + Group ID (JID)</strong> — Klik jumlah grup di dashboard untuk melihat detail lengkap dengan JID.</li>' +
        '<li><strong>Webhook Location &amp; Contact</strong> — Pesan lokasi dan kontak (vCard) di-forward ke webhook dengan data lengkap.</li>' +
        '<li><strong>History per Session</strong> — Log pesan masuk/keluar dengan filter dan paginasi.</li>' +
        '<li><strong>Health Check per Session</strong> — Pantau status, WA state, versi, dan info device.</li>' +
        '<li><strong>Server &amp; Service Monitor</strong> — CPU, RAM, disk, uptime, process memory di dashboard.</li>' +
        '<li><strong>Release Notes</strong> — Halaman riwayat versi dan perubahan.</li>' +
        '</ul>' +
        '<div style="font-weight:600;font-size:.85rem;color:#0369a1;margin-bottom:6px">📖 API Manual</div>' +
        '<ul style="padding-left:20px;font-size:.85rem;line-height:1.8;margin:0 0 12px">' +
        '<li>Contoh kode: cURL, PHP CodeIgniter, JavaScript fetch.</li>' +
        '<li>Dokumentasi webhook payload lengkap (teks, lokasi, grup).</li>' +
        '</ul>' +
        '</div></div>' +

        '<div class="card" style="margin-bottom:16px;border-left:4px solid #94a3b8">' +
        '<div class="card-header" style="padding-bottom:4px"><span class="card-title" style="font-size:1rem">v1.0.0</span>' +
        '<span style="font-size:.75rem;color:#6b7280;margin-left:10px">Initial Release</span></div>' +
        '<div class="card-body" style="padding-top:4px">' +
        '<ul style="padding-left:20px;font-size:.85rem;line-height:1.8;margin:0">' +
        '<li>Migrasi dari Baileys ke <strong>whatsapp-web.js</strong>.</li>' +
        '<li>Multi-session WhatsApp (scan QR / kode pairing).</li>' +
        '<li>Dashboard real-time dengan auto-refresh.</li>' +
        '<li>REST API: send, sendGroup, send-image, delete, kick, groups, group-members.</li>' +
        '<li>Autoreply keyword/regex, Webhook per session, Contact management, Broadcast.</li>' +
        '</ul>' +
        '</div></div>' +

        '</div>';
    res.send(layout('Release Notes', 'Riwayat perubahan aplikasi', body, 'release-notes'));
});

// ─── MARKETING TOOLS (PUBLIC) ─────────────────────────────────────────────────
app.get('/marketing-tools', (req, res) => {
    const html = `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Integrasi WA dan AI — WhatsApp Gateway + AI Integration</title>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1f2937;background:#fff;overflow-x:hidden;}
a{color:inherit;text-decoration:none;}

/* Animated Background */
@keyframes float{0%,100%{transform:translateY(0) rotate(0deg);}50%{transform:translateY(-20px) rotate(5deg);}}
@keyframes pulse{0%,100%{opacity:.4;}50%{opacity:.8;}}
@keyframes slideUp{from{opacity:0;transform:translateY(30px);}to{opacity:1;transform:translateY(0);}}
@keyframes gradientShift{0%{background-position:0% 50%;}50%{background-position:100% 50%;}100%{background-position:0% 50%;}}

/* Hero */
.hero{background:linear-gradient(135deg,#064e3b 0%,#065f46 25%,#047857 50%,#059669 75%,#10b981 100%);background-size:200% 200%;animation:gradientShift 8s ease infinite;padding:100px 24px 80px;text-align:center;color:#fff;position:relative;overflow:hidden;}
.hero::before{content:'';position:absolute;top:-100px;right:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(52,211,153,.2),transparent 70%);animation:float 6s ease-in-out infinite;pointer-events:none;}
.hero::after{content:'';position:absolute;bottom:-80px;left:-80px;width:400px;height:400px;background:radial-gradient(circle,rgba(16,185,129,.15),transparent 70%);animation:float 8s ease-in-out infinite reverse;pointer-events:none;}
.hero-inner{position:relative;z-index:1;max-width:800px;margin:0 auto;animation:slideUp .8s ease;}
.hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:24px;padding:6px 18px;font-size:.75rem;font-weight:600;letter-spacing:.04em;margin-bottom:24px;backdrop-filter:blur(8px);}
.hero-badge-dot{width:8px;height:8px;border-radius:50%;background:#34d399;animation:pulse 2s infinite;}
.hero h1{font-size:3rem;font-weight:900;margin-bottom:16px;line-height:1.15;letter-spacing:-.02em;}
.hero h1 .grad{background:linear-gradient(90deg,#6ee7b7,#34d399,#6ee7b7);background-size:200%;animation:gradientShift 3s ease infinite;-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.hero .sub{font-size:1.15rem;color:#a7f3d0;max-width:640px;margin:0 auto 32px;line-height:1.7;}
.hero-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-bottom:48px;}
.btn{padding:14px 32px;border-radius:12px;font-size:.9rem;font-weight:700;border:none;cursor:pointer;transition:all .3s;display:inline-flex;align-items:center;gap:8px;}
.btn-primary{background:#fff;color:#064e3b;box-shadow:0 4px 20px rgba(0,0,0,.15);} .btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(0,0,0,.2);}
.btn-ghost{background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(4px);} .btn-ghost:hover{background:rgba(255,255,255,.2);transform:translateY(-1px);}
.hero-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;max-width:700px;margin:0 auto;}
.hero-stat{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:20px 12px;backdrop-filter:blur(8px);transition:.3s;}
.hero-stat:hover{background:rgba(255,255,255,.14);transform:translateY(-3px);}
.hero-stat-val{font-size:2rem;font-weight:900;}
.hero-stat-lbl{font-size:.72rem;color:#a7f3d0;margin-top:4px;letter-spacing:.03em;}

/* Section */
.section{padding:80px 24px;max-width:1100px;margin:0 auto;}
.section-tag{display:inline-block;background:#d1fae5;color:#065f46;padding:4px 14px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px;}
.section-title{text-align:center;margin-bottom:48px;}
.section-title h2{font-size:1.8rem;font-weight:800;margin-bottom:10px;letter-spacing:-.01em;}
.section-title p{color:#6b7280;font-size:.95rem;max-width:500px;margin:0 auto;line-height:1.6;}
.section-alt{background:#f8fafc;}

/* Feature Grid */
.ft-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;}
.ft-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:28px 24px;transition:all .3s;position:relative;overflow:hidden;}
.ft-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#10b981,#06b6d4);opacity:0;transition:.3s;}
.ft-card:hover{border-color:#10b981;box-shadow:0 12px 40px rgba(16,185,129,.12);transform:translateY(-4px);}
.ft-card:hover::before{opacity:1;}
.ft-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:16px;}
.ft-icon-green{background:linear-gradient(135deg,#d1fae5,#a7f3d0);}
.ft-icon-blue{background:linear-gradient(135deg,#dbeafe,#bfdbfe);}
.ft-icon-amber{background:linear-gradient(135deg,#fef3c7,#fde68a);}
.ft-icon-purple{background:linear-gradient(135deg,#ede9fe,#ddd6fe);}
.ft-icon-red{background:linear-gradient(135deg,#fee2e2,#fecaca);}
.ft-icon-cyan{background:linear-gradient(135deg,#cffafe,#a5f3fc);}
.ft-card h3{font-size:1.05rem;font-weight:700;margin-bottom:8px;}
.ft-card p{font-size:.85rem;color:#6b7280;line-height:1.65;}
.ft-tags{display:flex;gap:6px;margin-top:14px;flex-wrap:wrap;}
.ft-tag{display:inline-block;font-size:.62rem;font-weight:700;padding:3px 10px;border-radius:6px;text-transform:uppercase;letter-spacing:.05em;}
.tag-api{background:#dbeafe;color:#1e40af;} .tag-ui{background:#d1fae5;color:#065f46;} .tag-auto{background:#fef3c7;color:#92400e;} .tag-ai{background:#ede9fe;color:#5b21b6;} .tag-new{background:#fce7f3;color:#9d174d;}

/* API Section */
.api-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;}
.api-item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;display:flex;align-items:flex-start;gap:12px;transition:.2s;}
.api-item:hover{border-color:#0ea5e9;box-shadow:0 4px 16px rgba(14,165,233,.08);}
.api-method{font-size:.62rem;font-weight:800;padding:4px 10px;border-radius:6px;font-family:'SF Mono',Consolas,monospace;flex-shrink:0;margin-top:2px;}
.api-get{background:#dbeafe;color:#1d4ed8;} .api-post{background:#fef3c7;color:#92400e;}
.api-item-body h4{font-size:.85rem;font-weight:600;margin-bottom:3px;}
.api-item-body p{font-size:.75rem;color:#6b7280;line-height:1.4;}
.api-path{font-family:'SF Mono',Consolas,monospace;font-size:.7rem;color:#0ea5e9;margin-bottom:4px;display:block;}

/* Webhook */
.wh-box{background:linear-gradient(135deg,#0f172a,#1e293b,#1e1b4b);border-radius:20px;padding:40px 36px;color:#fff;display:grid;grid-template-columns:1fr 1fr;gap:36px;align-items:center;position:relative;overflow:hidden;}
.wh-box::before{content:'';position:absolute;top:-50%;right:-20%;width:400px;height:400px;background:radial-gradient(circle,rgba(99,102,241,.15),transparent);pointer-events:none;}
.wh-box-text h3{font-size:1.3rem;font-weight:800;margin-bottom:12px;}
.wh-box-text p{font-size:.88rem;color:#94a3b8;line-height:1.7;margin-bottom:16px;}
.wh-tags{display:flex;gap:8px;flex-wrap:wrap;}
.wh-tag{display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:8px;padding:6px 12px;font-size:.72rem;font-weight:600;transition:.2s;}
.wh-tag:hover{background:rgba(255,255,255,.15);}
.wh-box-code{background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:20px 22px;font-family:'SF Mono',Consolas,monospace;font-size:.7rem;color:#a5b4fc;line-height:1.8;white-space:pre-wrap;word-break:break-all;max-height:320px;overflow-y:auto;position:relative;z-index:1;}

/* AI Section */
.ai-box{display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:center;}
.ai-steps{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:32px 28px;}
.ai-steps h3{font-size:1.1rem;font-weight:700;margin-bottom:20px;}
.ai-step{display:flex;gap:14px;align-items:flex-start;margin-bottom:18px;}
.ai-step-num{width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;flex-shrink:0;}
.ai-step-text{font-size:.88rem;line-height:1.6;color:#374151;}
.ai-step-text strong{color:#5b21b6;}
.ai-info{background:linear-gradient(135deg,#ede9fe,#f5f3ff);border-radius:14px;padding:20px 22px;margin-top:16px;}
.ai-info p{font-size:.82rem;color:#5b21b6;line-height:1.7;}
.ai-code{background:#1e293b;border-radius:14px;padding:24px 22px;color:#94a3b8;font-family:'SF Mono',Consolas,monospace;font-size:.72rem;line-height:1.8;white-space:pre-wrap;max-height:360px;overflow-y:auto;}

/* Tech Stack */
.tech-grid{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;}
.tech-pill{padding:12px 24px;border-radius:12px;font-weight:700;font-size:.85rem;border:1px solid;transition:.3s;cursor:default;}
.tech-pill:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.08);}

/* CTA */
.cta{background:linear-gradient(135deg,#064e3b 0%,#047857 50%,#059669 100%);padding:80px 24px;text-align:center;color:#fff;position:relative;overflow:hidden;}
.cta::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");pointer-events:none;}
.cta h2{font-size:2rem;font-weight:900;margin-bottom:12px;position:relative;}
.cta p{color:#a7f3d0;font-size:1rem;margin-bottom:32px;max-width:520px;margin-left:auto;margin-right:auto;line-height:1.7;position:relative;}
.cta .btn{position:relative;font-size:1rem;padding:16px 36px;}

/* Footer */
.mkt-footer{background:#022c22;padding:28px;text-align:center;color:#6ee7b7;font-size:.75rem;letter-spacing:.02em;}
.mkt-footer a{color:#34d399;text-decoration:underline;}

@media(max-width:768px){.hero h1{font-size:2rem;}.hero-stats{grid-template-columns:repeat(2,1fr);gap:12px;}.wh-box{grid-template-columns:1fr;}.ai-box{grid-template-columns:1fr;}.hero-stat{padding:14px 8px;}}
@media(max-width:480px){.hero h1{font-size:1.6rem;}.hero{padding:60px 16px 50px;}.section{padding:50px 16px;}.ft-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>

<!-- HERO -->
<div class="hero">
  <div class="hero-inner">
    <div class="hero-badge"><span class="hero-badge-dot"></span> v${APP_VERSION} — Production Ready</div>
    <h1>📱 Integrasi WA dan AI<br><span class="grad">Gateway WhatsApp + Kecerdasan Buatan</span></h1>
    <p class="sub">Platform all-in-one untuk mengirim pesan, mengelola grup, autoreply cerdas, broadcast massal, webhook real-time — semua terintegrasi langsung dengan AI favorit Anda.</p>
    <div class="hero-btns">
      <a href="/login" class="btn btn-primary">🚀 Masuk Dashboard</a>
      <a href="/api-manual" class="btn btn-ghost">📖 Dokumentasi API</a>
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><div class="hero-stat-val">18+</div><div class="hero-stat-lbl">REST API Endpoints</div></div>
      <div class="hero-stat"><div class="hero-stat-val">∞</div><div class="hero-stat-lbl">Multi Nomor WA</div></div>
      <div class="hero-stat"><div class="hero-stat-val">24/7</div><div class="hero-stat-lbl">Always Connected</div></div>
      <div class="hero-stat"><div class="hero-stat-val">🤖</div><div class="hero-stat-lbl">AI-Powered</div></div>
    </div>
  </div>
</div>

<!-- FITUR UTAMA -->
<div class="section">
  <div class="section-title">
    <span class="section-tag">Fitur Lengkap</span>
    <h2>Semua yang Anda Butuhkan, dalam Satu Platform</h2>
    <p>Dari kirim pesan sederhana hingga integrasi AI canggih — semuanya siap pakai.</p>
  </div>
  <div class="ft-grid">

    <div class="ft-card">
      <div class="ft-icon ft-icon-green">📱</div>
      <h3>Multi Device / Multi Nomor</h3>
      <p>Hubungkan banyak nomor WhatsApp sekaligus. Scan QR atau pakai Kode Pairing — semua dikelola dari satu dashboard yang intuitif.</p>
      <div class="ft-tags"><span class="ft-tag tag-ui">Dashboard</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-blue">💬</div>
      <h3>Kirim Pesan, Gambar &amp; Lokasi</h3>
      <p>Kirim pesan teks, gambar + caption, atau lokasi (latitude/longitude) ke nomor personal via REST API. Sempurna untuk OTP, invoice, dan tracking.</p>
      <div class="ft-tags"><span class="ft-tag tag-api">REST API</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-amber">👥</div>
      <h3>Manajemen Grup Lengkap</h3>
      <p>Daftar grup, kirim pesan ke grup, lihat anggota + role, kick member, leave group, buat grup baru, refresh cache — semua tersedia via API dan dashboard.</p>
      <div class="ft-tags"><span class="ft-tag tag-api">REST API</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-purple">🤖</div>
      <h3>AI-Ready Integration</h3>
      <p>Satu klik copy instruksi lengkap ke ChatGPT, Claude, atau Gemini. AI langsung bisa mengirim pesan dan kelola grup via API Anda secara otomatis.</p>
      <div class="ft-tags"><span class="ft-tag tag-ai">AI Integration</span><span class="ft-tag tag-new">🔥 Baru</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-cyan">🔗</div>
      <h3>Webhook Real-Time</h3>
      <p>Setiap pesan masuk (teks, gambar, lokasi, kontak, dari grup) otomatis di-forward ke webhook URL Anda secara instan dan real-time.</p>
      <div class="ft-tags"><span class="ft-tag tag-api">Webhook</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-green">🔄</div>
      <h3>Autoreply Otomatis</h3>
      <p>Buat aturan autoreply cerdas dengan keyword atau regex. Pesan masuk yang cocok langsung dibalas otomatis tanpa campur tangan manusia.</p>
      <div class="ft-tags"><span class="ft-tag tag-auto">Otomasi</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-blue">📢</div>
      <h3>Broadcast Massal</h3>
      <p>Kirim pesan ke ratusan nomor sekaligus. Kelola kontak, atur broadcast job, dan pantau riwayat pengiriman dari dashboard.</p>
      <div class="ft-tags"><span class="ft-tag tag-ui">Dashboard</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-red">🛡️</div>
      <h3>Moderasi Grup</h3>
      <p>Hapus pesan di grup (untuk semua anggota) dan kick member langsung dari API. Bot harus menjadi admin grup untuk fitur ini.</p>
      <div class="ft-tags"><span class="ft-tag tag-api">REST API</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-amber">📜</div>
      <h3>Message History &amp; Logging</h3>
      <p>Semua pesan masuk &amp; keluar tercatat otomatis di database PostgreSQL. Lihat history per session lengkap dengan filter dan paginasi.</p>
      <div class="ft-tags"><span class="ft-tag tag-ui">Dashboard</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-purple">📍</div>
      <h3>Location &amp; Contact Support</h3>
      <p>Pesan lokasi (current / live) dan kontak (vCard) terdeteksi otomatis dan di-forward ke webhook lengkap dengan data koordinat GPS.</p>
      <div class="ft-tags"><span class="ft-tag tag-api">Webhook</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-cyan">🔑</div>
      <h3>Pairing Code &amp; QR Scan</h3>
      <p>Dua cara hubungkan device: scan QR code atau masukkan kode pairing 8-digit. Dilengkapi auto-reconnect jika koneksi terputus.</p>
      <div class="ft-tags"><span class="ft-tag tag-ui">Dashboard</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-green">🩺</div>
      <h3>Health Check &amp; Monitoring</h3>
      <p>Pantau kesehatan server (CPU, RAM, disk, uptime) dan health per session WhatsApp secara real-time dari dashboard.</p>
      <div class="ft-tags"><span class="ft-tag tag-ui">Dashboard</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-red">🔔</div>
      <h3>Notifikasi Disconnect Otomatis</h3>
      <p>Saat sesi WA terputus, sistem otomatis mengirim notifikasi ke admin via <strong>WhatsApp</strong> dan <strong>Email</strong>. Dilengkapi debounce 60 detik untuk mencegah spam saat auto-reconnect.</p>
      <div class="ft-tags"><span class="ft-tag tag-auto">Otomasi</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-purple">📊</div>
      <h3>Register Date &amp; Uptime Koneksi</h3>
      <p>Setiap nomor WA di dashboard menampilkan tanggal pertama kali didaftarkan dan durasi sesi koneksi berjalan (uptime) secara real-time.</p>
      <div class="ft-tags"><span class="ft-tag tag-ui">Dashboard</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-blue">📲</div>
      <h3>Pesan Interaktif — Buttons &amp; List</h3>
      <p>Kirim pesan dengan <strong>reply buttons</strong> (maks 3 tombol) atau <strong>list menu</strong> bertingkat. Penerima bisa tap untuk memilih langsung dari WhatsApp.</p>
      <div class="ft-tags"><span class="ft-tag tag-api">REST API</span><span class="ft-tag tag-new">🔥 Baru</span></div>
    </div>

    <div class="ft-card">
      <div class="ft-icon ft-icon-amber">📊</div>
      <h3>Polling / Voting</h3>
      <p>Buat polling interaktif dengan min 2 maks 12 pilihan. Support single choice maupun multi choice. Penerima vote langsung dari dalam WhatsApp.</p>
      <div class="ft-tags"><span class="ft-tag tag-api">REST API</span><span class="ft-tag tag-new">🔥 Baru</span></div>
    </div>

  </div>
</div>

<!-- API ENDPOINTS -->
<div class="section-alt">
<div class="section">
  <div class="section-title">
    <span class="section-tag">Developer Friendly</span>
    <h2>⚡ REST API Endpoints</h2>
    <p>18+ endpoint siap pakai dengan autentikasi Bearer Token dan response JSON.</p>
  </div>
  <div class="api-grid">
    <div class="api-item"><span class="api-method api-get">GET</span><div class="api-item-body"><span class="api-path">/api/status</span><h4>Status Session</h4><p>Cek status semua session WA yang aktif</p></div></div>
    <div class="api-item"><span class="api-method api-get">GET</span><div class="api-item-body"><span class="api-path">/api/qr/:id</span><h4>QR Code</h4><p>Dapatkan QR code untuk scan ulang</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/send</span><h4>Kirim Pesan Teks</h4><p>Kirim pesan ke nomor personal</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/send-image</span><h4>Kirim Gambar</h4><p>Kirim gambar + caption ke personal</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/send-location</span><h4>Kirim Lokasi</h4><p>Kirim titik koordinat ke personal</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/send-buttons</span><h4>Kirim Pesan + Tombol</h4><p>Kirim pesan dengan reply buttons (maks 3)</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/send-list</span><h4>Kirim List Menu</h4><p>Kirim pesan dengan daftar pilihan menu</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/send-poll</span><h4>Kirim Polling</h4><p>Kirim polling/voting ke personal</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/sendGroup</span><h4>Kirim ke Grup</h4><p>Kirim pesan ke grup WA</p></div></div>
    <div class="api-item"><span class="api-method api-get">GET</span><div class="api-item-body"><span class="api-path">/api/groups</span><h4>Daftar Grup</h4><p>List semua grup + jumlah anggota</p></div></div>
    <div class="api-item"><span class="api-method api-get">GET</span><div class="api-item-body"><span class="api-path">/api/group-members</span><h4>Anggota Grup</h4><p>Detail anggota + role (admin/member)</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/refresh-groups</span><h4>Refresh Grup</h4><p>Refresh cache daftar grup</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/delete</span><h4>Hapus Pesan</h4><p>Hapus pesan di grup (untuk semua)</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/kick</span><h4>Kick Member</h4><p>Keluarkan anggota dari grup</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/leave-group</span><h4>Leave Group</h4><p>Keluar dari grup WhatsApp</p></div></div>
    <div class="api-item"><span class="api-method api-post">POST</span><div class="api-item-body"><span class="api-path">/api/create-group</span><h4>Buat Grup</h4><p>Buat grup baru dengan nama &amp; peserta</p></div></div>
  </div>
</div>
</div>

<!-- WEBHOOK -->
<div class="section">
  <div class="section-title">
    <span class="section-tag">Real-Time</span>
    <h2>🔔 Webhook Incoming Message</h2>
    <p>Terima semua pesan masuk ke server Anda secara instan</p>
  </div>
  <div class="wh-box">
    <div class="wh-box-text">
      <h3>Incoming Message Webhook</h3>
      <p>Setiap pesan yang masuk — teks, gambar, video, lokasi, kontak, dari personal maupun grup — langsung di-POST ke URL webhook Anda dalam hitungan milidetik.</p>
      <div class="wh-tags">
        <span class="wh-tag">📝 Teks</span><span class="wh-tag">🖼️ Gambar</span><span class="wh-tag">🎥 Video</span>
        <span class="wh-tag">🎙️ Audio</span><span class="wh-tag">📍 Lokasi</span><span class="wh-tag">👤 Kontak</span>
        <span class="wh-tag">📎 Dokumen</span><span class="wh-tag">👥 Grup</span>
      </div>
    </div>
    <div class="wh-box-code">{
  "phone_id": "6281317647379",
  "message_id": "3EB0xxxx",
  "message": "Halo, apa kabar?",
  "type": "conversation",
  "timestamp": 1741392000,
  "sender": "6281234567890",
  "sender_name": "John",
  "location": {
    "latitude": -6.2088,
    "longitude": 106.8456
  },
  "group_id": "120363xxx@g.us",
  "group_name": "Grup Kerja"
}</div>
  </div>
</div>

<!-- AI READY -->
<div class="section-alt">
<div class="section">
  <div class="section-title">
    <span class="section-tag">🤖 AI-Powered</span>
    <h2>Integrasi AI dalam Satu Klik</h2>
    <p>AI favorit Anda langsung mengerti semua API WhatsApp Anda</p>
  </div>
  <div class="ai-box">
    <div class="ai-steps">
      <h3>Cara Kerja Integrasi AI:</h3>
      <div class="ai-step"><div class="ai-step-num">1</div><div class="ai-step-text">Buka Dashboard → klik tombol <strong>🤖 AI</strong> pada device</div></div>
      <div class="ai-step"><div class="ai-step-num">2</div><div class="ai-step-text">Instruksi API lengkap <strong>otomatis ter-copy</strong> ke clipboard</div></div>
      <div class="ai-step"><div class="ai-step-num">3</div><div class="ai-step-text">Paste ke <strong>ChatGPT / Claude / Gemini</strong> atau AI lainnya</div></div>
      <div class="ai-step"><div class="ai-step-num">4</div><div class="ai-step-text">AI langsung bisa <strong>mengirim pesan, kelola grup, broadcast</strong> via WA</div></div>
      <div class="ai-info">
        <p><strong>📋 Isi instruksi mencakup:</strong><br>
        ✅ Base URL + Bearer Token (pre-filled)<br>
        ✅ 13 endpoint lengkap + contoh cURL<br>
        ✅ Format webhook payload + contoh JSON<br>
        ✅ Catatan penting (format nomor, rate limit)</p>
      </div>
    </div>
    <div class="ai-code"># Integrasi WA dan AI
# WhatsApp Gateway API v${APP_VERSION}

## Konfigurasi
- Base URL: https://integrasi-wa.jodyaryono.id
- Phone ID: 6281317647379
- Authorization: Bearer fc42fe461f...

## Endpoint API

### POST /api/send
Kirim pesan teks ke nomor personal.
Body: { phone_id, number, message }

### POST /api/sendGroup
Kirim pesan ke grup WhatsApp.
Body: { phone_id, group, message }

### GET /api/groups
Daftar semua grup WhatsApp.

... dan 10 endpoint lainnya + webhook docs</div>
  </div>
</div>
</div>

<!-- TECH STACK -->
<div class="section">
  <div class="section-title">
    <span class="section-tag">Teknologi</span>
    <h2>🛠️ Dibangun dengan Stack Modern</h2>
    <p>Teknologi terbukti stabil untuk production</p>
  </div>
  <div class="tech-grid">
    <span class="tech-pill" style="background:#f0fdf4;border-color:#bbf7d0;color:#065f46">⬢ Node.js 20</span>
    <span class="tech-pill" style="background:#f0fdf4;border-color:#bbf7d0;color:#065f46">🚀 Express.js</span>
    <span class="tech-pill" style="background:#dbeafe;border-color:#bfdbfe;color:#1e40af">💬 whatsapp-web.js</span>
    <span class="tech-pill" style="background:#dbeafe;border-color:#bfdbfe;color:#1e40af">🐘 PostgreSQL</span>
    <span class="tech-pill" style="background:#fef3c7;border-color:#fde68a;color:#92400e">🌐 Puppeteer/Chrome</span>
    <span class="tech-pill" style="background:#ede9fe;border-color:#c4b5fd;color:#5b21b6">🔌 REST API + Webhook</span>
    <span class="tech-pill" style="background:#fce7f3;border-color:#fbcfe8;color:#9d174d">🤖 AI-Ready</span>
  </div>
</div>

<!-- CTA -->
<div class="cta">
  <h2>Siap Mengintegrasikan WhatsApp + AI?</h2>
  <p>Hubungkan nomor WhatsApp Anda dan mulai kirim pesan via API dalam hitungan menit. Gratis dan open source.</p>
  <a href="/login" class="btn btn-primary">🚀 Mulai Sekarang</a>
</div>

<!-- Footer -->
<div class="mkt-footer">
  📱 Integrasi WA dan AI v${APP_VERSION} — WhatsApp Gateway + AI Integration<br>
  <span style="color:#34d399">© ${new Date().getFullYear()} <a href="https://jodyaryono.id">jodyaryono.id</a></span>
</div>

</body>
</html>`;
    res.send(html);
});

// ─── ROOT & MISC ──────────────────────────────────────────────────────────────
app.get('/', (req, res) => { if (req.session?.loggedIn) return res.redirect('/dashboard'); return res.redirect('/login'); });
app.get('/webhook', (req, res) => res.json({ webhook_url: WEBHOOK_URL || 'not configured', webhook_enabled: WEBHOOK_ENABLED }));

// Test email notification (admin only)
app.get('/web/test-email', requireLogin, async (req, res) => {
    try {
        await mailer.sendMail({
            from: `Integrasi WA <${SMTP_USER}>`,
            to: NOTIFY_EMAIL,
            subject: '✅ Test Notifikasi Email — Integrasi WA',
            html: `<div style="font-family:sans-serif;max-width:500px">
<h2 style="color:#16a34a">✅ Email Notifikasi Berfungsi</h2>
<p>Email ini dikirim dari <strong>Integrasi WA</strong> untuk memastikan notifikasi disconnect berjalan dengan baik.</p>
<p>Waktu: ${new Date().toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })} WIB</p>
</div>`,
        });
        res.json({ success: true, message: 'Test email sent to ' + NOTIFY_EMAIL });
    } catch (e) {
        res.status(500).json({ success: false, error: e.message });
    }
});

// ─── AI INSTRUCTIONS ENDPOINT ─────────────────────────────────────────────────
app.get('/web/session/:id/ai-instructions', requireLogin, (req, res) => {
    const id = sanitizeId(req.params.id);
    const s = sessions.get(id);
    if (!s) return res.status(404).json({ error: 'Session not found' });
    const baseUrl = req.protocol + '://' + req.get('host');
    const token = s.apiToken || 'TOKEN_BELUM_ADA';
    const phoneId = id;
    const text = `# Integrasi WA — WhatsApp Gateway API (v${APP_VERSION})

Kamu adalah asisten yang terhubung ke WhatsApp Gateway "Integrasi WA".
Gunakan informasi berikut untuk mengirim pesan, mengelola grup, dan berinteraksi via WhatsApp.

## Konfigurasi
- Base URL: ${baseUrl}
- Phone ID: ${phoneId}
- Authorization: Bearer ${token}
- Content-Type: application/json

Semua request API harus menyertakan header:
\`\`\`
Authorization: Bearer ${token}
Content-Type: application/json
\`\`\`

---

## ENDPOINT API

### 1. GET /api/status
Cek status semua session WhatsApp yang aktif.
\`\`\`bash
curl -X GET "${baseUrl}/api/status" -H "Authorization: Bearer ${token}"
\`\`\`
Response: { sessions: { "${phoneId}": { label, status, groups_cached } }, uptime }

### 2. GET /api/qr/:id
Dapatkan QR code untuk scan ulang session.
\`\`\`bash
curl -X GET "${baseUrl}/api/qr/${phoneId}" -H "Authorization: Bearer ${token}"
\`\`\`
Response: { status: "connecting"|"open"|"waiting", qr: "data:image/png;base64,..." | null }

### 3. POST /api/send
Kirim pesan teks ke nomor personal.
\`\`\`bash
curl -X POST "${baseUrl}/api/send" \\
  -H "Authorization: Bearer ${token}" \\
  -H "Content-Type: application/json" \\
  -d '{"phone_id":"${phoneId}","number":"628xxx","message":"Halo!"}'
\`\`\`
Body:
- phone_id (string, opsional): Phone ID pengirim. Default: "${phoneId}"
- number (string, WAJIB): Nomor tujuan format 628xxx
- message (string, WAJIB): Isi pesan
Response: { success: true, phone_id, data: { id: "3EB0xxxx" } }

### 4. POST /api/send-image
Kirim gambar dengan caption ke nomor personal.
\`\`\`bash
curl -X POST "${baseUrl}/api/send-image" \\
  -H "Authorization: Bearer ${token}" \\
  -H "Content-Type: application/json" \\
  -d '{"phone_id":"${phoneId}","number":"628xxx","image":"https://example.com/foto.jpg","message":"Caption"}'
\`\`\`
Body:
- phone_id (string, opsional): Default "${phoneId}"
- number (string, WAJIB): Nomor tujuan 628xxx
- image (string, WAJIB): URL gambar (https://...)
- message (string, opsional): Caption gambar
Response: { success: true, phone_id, data: { id: "3EB0xxxx" } }

### 5. POST /api/send-location
Kirim lokasi (latitude/longitude) ke nomor personal.
\`\`\`bash
curl -X POST "${baseUrl}/api/send-location" \\
  -H "Authorization: Bearer ${token}" \\
  -H "Content-Type: application/json" \\
  -d '{"phone_id":"${phoneId}","number":"628xxx","latitude":-6.2088,"longitude":106.8456,"description":"Monas, Jakarta"}'
\`\`\`
Body:
- phone_id (string, opsional): Default "${phoneId}"
- number (string, WAJIB): Nomor tujuan 628xxx
- latitude (number, WAJIB): Latitude lokasi
- longitude (number, WAJIB): Longitude lokasi
- description (string, opsional): Nama/deskripsi lokasi
Response: { success: true, phone_id, data: { id: "3EB0xxxx" } }

### 6. POST /api/send-buttons
Kirim pesan dengan reply buttons (maks 3 tombol). ⚠️ Buttons mungkin tidak tampil di semua versi WA.
\`\`\`bash
curl -X POST "${baseUrl}/api/send-buttons" \\
  -H "Authorization: Bearer ${token}" \\
  -H "Content-Type: application/json" \\
  -d '{"phone_id":"${phoneId}","number":"628xxx","message":"Apakah Anda setuju?","footer":"Pilih salah satu","buttons":[{"id":"btn1","text":"Ya"},{"id":"btn2","text":"Tidak"}]}'
\`\`\`
Body:
- phone_id (string, opsional): Default "${phoneId}"
- number (string, WAJIB): Nomor tujuan 628xxx
- message (string, WAJIB): Isi pesan utama
- buttons (array, WAJIB): Array tombol [{id,text}] maks 3
- footer (string, opsional): Teks footer
Response: { success: true, phone_id, data: { id: "3EB0xxxx" } }

### 7. POST /api/send-list
Kirim pesan dengan list/menu pilihan. ⚠️ List mungkin tidak tampil di semua versi WA.
\`\`\`bash
curl -X POST "${baseUrl}/api/send-list" \\
  -H "Authorization: Bearer ${token}" \\
  -H "Content-Type: application/json" \\
  -d '{"phone_id":"${phoneId}","number":"628xxx","message":"Pilih layanan:","buttonText":"Lihat Menu","title":"Menu","sections":[{"title":"Layanan","rows":[{"id":"1","title":"Konsultasi","description":"Gratis"},{"id":"2","title":"Order"}]}]}'
\`\`\`
Body:
- phone_id (string, opsional): Default "${phoneId}"
- number (string, WAJIB): Nomor tujuan 628xxx
- message (string, WAJIB): Isi pesan utama
- buttonText (string, WAJIB): Label tombol menu
- sections (array, WAJIB): Array section [{title,rows:[{id,title,description?}]}]
- title (string, opsional): Judul list
- footer (string, opsional): Teks footer
Response: { success: true, phone_id, data: { id: "3EB0xxxx" } }

### 8. POST /api/send-poll
Kirim polling/voting.
\`\`\`bash
curl -X POST "${baseUrl}/api/send-poll" \\
  -H "Authorization: Bearer ${token}" \\
  -H "Content-Type: application/json" \\
  -d '{"phone_id":"${phoneId}","number":"628xxx","question":"Mau makan apa?","options":["Nasi Goreng","Mie Ayam","Bakso"],"allowMultiple":false}'
\`\`\`
Body:
- phone_id (string, opsional): Default "${phoneId}"
- number (string, WAJIB): Nomor tujuan 628xxx
- question (string, WAJIB): Pertanyaan polling
- options (array, WAJIB): Pilihan jawaban (min 2, maks 12)
- allowMultiple (boolean, opsional): Boleh multi-pilih? Default: true
Response: { success: true, phone_id, data: { id: "3EB0xxxx" } }

### 9. GET /api/groups
Daftar semua grup WhatsApp yang ter-cache.
\`\`\`bash
curl -X GET "${baseUrl}/api/groups?phone_id=${phoneId}" -H "Authorization: Bearer ${token}"
\`\`\`
Query: phone_id (opsional)
Response: { phone_id, groups: [{ jid: "120363xxx@g.us", name: "Nama Grup", participants: 25 }] }

### 10. POST /api/refresh-groups
Refresh cache daftar grup dari WhatsApp.
\`\`\`bash
curl -X POST "${baseUrl}/api/refresh-groups" \\
  -H "Authorization: Bearer ${token}" \\
  -H "Content-Type: application/json" \\
  -d '{"phone_id":"${phoneId}"}'
\`\`\`
Response: { success: true, phone_id, groups: [...] }

### 11. GET /api/group-members
Ambil semua anggota grup beserta role (superadmin/admin/member).
\`\`\`bash
curl -X GET "${baseUrl}/api/group-members?phone_id=${phoneId}&group_id=120363xxx@g.us" \\
  -H "Authorization: Bearer ${token}"
\`\`\`
Query:
- phone_id (opsional): Default "${phoneId}"
- group_id (WAJIB): JID grup (format xxx@g.us)
Response: { phone_id, group_id, total, members: [{ number: "628xxx", role: "admin" }] }

### 12. POST /api/sendGroup
Kirim pesan ke grup WhatsApp.
\`\`\`bash
curl -X POST "${baseUrl}/api/sendGroup" \\
  -H "Authorization: Bearer ${token}" \\
  -H "Content-Type: application/json" \\
  -d '{"phone_id":"${phoneId}","group":"Nama Grup","message":"Pengumuman!"}'
\`\`\`
Body:
- phone_id (string, opsional): Default "${phoneId}"
- group (string, WAJIB): JID grup (xxx@g.us) ATAU nama grup (partial match)
- message (string, WAJIB): Isi pesan
Response: { success: true, phone_id, data: { id, group_jid } }
Catatan: Bisa pakai nama grup (partial match case-insensitive) atau JID lengkap.

### 13. POST /api/delete
Hapus pesan di grup (untuk semua anggota). Pesan harus ada di 50 pesan terakhir.
\`\`\`bash
curl -X POST "${baseUrl}/api/delete" \\
  -H "Authorization: Bearer ${token}" \\
  -H "Content-Type: application/json" \\
  -d '{"phone_id":"${phoneId}","group_id":"120363xxx@g.us","message_id":"3EB0abc","participant":"628xxx"}'
\`\`\`
Body:
- phone_id (opsional): Default "${phoneId}"
- group_id (WAJIB): JID atau nama grup
- message_id (WAJIB): ID pesan yang akan dihapus
- participant (opsional): Nomor pengirim pesan (jika hapus pesan orang lain)
Alternatif: kirim field "key" atau "_key" langsung berisi { remoteJid, id, fromMe, participant }
Response: { success: true, deleted: "3EB0abc" }

### 14. POST /api/kick
Keluarkan anggota dari grup. Bot harus jadi admin.
\`\`\`bash
curl -X POST "${baseUrl}/api/kick" \\
  -H "Authorization: Bearer ${token}" \\
  -H "Content-Type: application/json" \\
  -d '{"phone_id":"${phoneId}","group_id":"120363xxx@g.us","member":"628xxx"}'
\`\`\`
Body:
- phone_id (opsional): Default "${phoneId}"
- group_id (WAJIB): JID atau nama grup
- member (WAJIB): Nomor anggota yang di-kick (628xxx)
Response: { success: true, data: {} }
⚠️ Bot harus admin di grup tersebut.

---

## FORMAT WEBHOOK (Incoming Message)

Setiap pesan masuk di-POST ke webhook URL yang dikonfigurasi per session.

Fields:
| Field | Tipe | Keterangan |
|-------|------|------------|
| phone_id | string | Phone ID penerima (session) |
| message_id | string | ID unik pesan WhatsApp |
| message | string | Isi teks pesan |
| type | string | conversation, image, video, audio, document, sticker, location, contact |
| timestamp | number | Unix timestamp (detik) |
| sender | string | Nomor pengirim (628xxx) |
| sender_name | string/null | Push name pengirim |
| from | string | JID pengirim tanpa suffix |
| pushname | string/null | Push name |
| location | object/undefined | Jika type=location: { latitude, longitude, description, url } |
| group_id | string/undefined | Jika dari grup: JID grup (xxx@g.us) |
| from_group | string/undefined | Sama dengan group_id |
| group_name | string/undefined | Nama grup |
| _key | object | { remoteJid, id, fromMe, participant } — bisa dipakai untuk delete/reply |

Contoh payload pesan teks personal:
\`\`\`json
{
  "phone_id": "${phoneId}",
  "message_id": "3EB0xxxx",
  "message": "Halo, apa kabar?",
  "type": "conversation",
  "timestamp": 1741392000,
  "sender": "6281234567890",
  "sender_name": "John",
  "from": "6281234567890",
  "pushname": "John",
  "_key": { "remoteJid": "6281234567890@c.us", "id": "3EB0xxxx", "fromMe": false }
}
\`\`\`

Contoh payload lokasi:
\`\`\`json
{
  "phone_id": "${phoneId}",
  "message_id": "3EB0xxxx",
  "message": "",
  "type": "location",
  "timestamp": 1741392000,
  "sender": "6281234567890",
  "sender_name": "John",
  "location": {
    "latitude": -6.2088,
    "longitude": 106.8456,
    "description": "Monas, Jakarta",
    "url": "https://maps.google.com/..."
  },
  "_key": { "remoteJid": "6281234567890@c.us", "id": "3EB0xxxx", "fromMe": false }
}
\`\`\`

Contoh payload pesan grup:
\`\`\`json
{
  "phone_id": "${phoneId}",
  "message_id": "3EB0xxxx",
  "message": "Pengumuman!",
  "type": "conversation",
  "timestamp": 1741392000,
  "sender": "6281234567890",
  "sender_name": "John",
  "from": "120363xxx",
  "group_id": "120363xxx@g.us",
  "from_group": "120363xxx@g.us",
  "group_name": "Grup Kerja",
  "_key": { "remoteJid": "120363xxx@g.us", "id": "3EB0xxxx", "fromMe": false, "participant": "6281234567890@c.us" }
}
\`\`\`

---

## CATATAN PENTING
- Semua nomor WhatsApp format internasional tanpa + (contoh: 6281234567890)
- phone_id bersifat opsional — jika tidak diisi, akan pakai session pertama yang connected
- Untuk grup bisa pakai nama (partial match) atau JID lengkap (xxx@g.us)
- Rate limit: jangan kirim pesan terlalu cepat, beri jeda minimal 1-2 detik antar pesan
- Bot harus admin untuk kick anggota grup
- Delete hanya bisa untuk 50 pesan terakhir dalam chat
`;
    res.json({ text });
});

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
// Temporary debug endpoint — diagnose Chrome responsiveness
app.get('/api/debug/:id', apiAuth, async (req, res) => {
    const id = sanitizeId(req.params.id);
    const s = sessions.get(id);
    if (!s || !s.client) return res.status(404).json({ error: 'Session not found or no client' });
    const results = {};
    // Test 1: basic page.evaluate
    try {
        const t0 = Date.now();
        const title = await Promise.race([s.client.pupPage.evaluate(() => document.title), new Promise((_, r) => setTimeout(() => r(new Error('timeout')), 15000))]);
        results.pageTitle = { ok: true, value: title, ms: Date.now() - t0 };
    } catch (e) { results.pageTitle = { ok: false, error: e.message }; }
    // Test 2: getState
    try {
        const t0 = Date.now();
        const st = await Promise.race([s.client.getState(), new Promise((_, r) => setTimeout(() => r(new Error('timeout')), 15000))]);
        results.getState = { ok: true, value: st, ms: Date.now() - t0 };
    } catch (e) { results.getState = { ok: false, error: e.message }; }
    // Test 3: WA store check
    try {
        const t0 = Date.now();
        const info = await Promise.race([s.client.pupPage.evaluate(() => {
            return { hasStore: typeof window.Store !== 'undefined', hasChat: typeof window.Store?.Chat !== 'undefined', hasMsgSend: typeof window.Store?.SendMessage !== 'undefined' || typeof window.Store?.Msg?.send !== 'undefined', readyState: document.readyState };
        }), new Promise((_, r) => setTimeout(() => r(new Error('timeout')), 15000))]);
        results.waStore = { ok: true, value: info, ms: Date.now() - t0 };
    } catch (e) { results.waStore = { ok: false, error: e.message }; }
    // Test 4: simple number check (isRegisteredUser)
    try {
        const t0 = Date.now();
        const reg = await Promise.race([s.client.isRegisteredUser('6285719195627@c.us'), new Promise((_, r) => setTimeout(() => r(new Error('timeout')), 15000))]);
        results.isRegistered = { ok: true, value: reg, ms: Date.now() - t0 };
    } catch (e) { results.isRegistered = { ok: false, error: e.message }; }
    res.json({ phone_id: id, status: s.status, results });
});
app.post('/api/send', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { number, message } = req.body;
        if (!number || !message) return res.status(400).json({ error: 'number and message required' });
        const slot = await acquireSendSlot(pid);
        if (!slot.ok) return res.status(429).json({ error: slot.error, retry_after_sec: Math.ceil((slot.waitMs || 60000) / 1000) });
        const result = await withTimeout(s.client.sendMessage(numberToJid(number), message), 60000, 'sendMessage timeout — Chrome terlalu lambat, coba lagi');
        try { await db.query('INSERT INTO messages_log(session_id,direction,from_number,to_number,message,media_type,status,wa_msg_id) VALUES($1,$2,$3,$4,$5,$6,$7,$8)', [pid, 'out', pid, number.replace(/\D/g, ''), message, 'text', 'sent', result.id?._serialized || '']); } catch { }
        res.json({ success: true, phone_id: pid, data: { id: result.id?._serialized } });
    } catch (e) { const _dead = handleDeadSession(sanitizeId(req.body.phone_id || '') || getFirstOpenSession(), sessions.get(sanitizeId(req.body.phone_id || '') || getFirstOpenSession()), e); res.status(_dead ? 503 : 500).json({ success: false, error: _dead ? 'Sesi terputus, sedang reconnect otomatis. Silakan coba lagi dalam beberapa detik.' : e.message, reconnecting: _dead || undefined }); }
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
        const slot = await acquireSendSlot(pid);
        if (!slot.ok) return res.status(429).json({ error: slot.error, retry_after_sec: Math.ceil((slot.waitMs || 60000) / 1000) });
        const result = await withTimeout(s.client.sendMessage(jid, message), 60000, 'sendMessage timeout — Chrome terlalu lambat, coba lagi');
        try { await db.query('INSERT INTO messages_log(session_id,direction,from_number,to_number,message,media_type,status,wa_msg_id) VALUES($1,$2,$3,$4,$5,$6,$7,$8)', [pid, 'out', pid, jid.replace(/@g\.us$/, ''), message, 'text', 'sent', result.id?._serialized || '']); } catch { }
        res.json({ success: true, phone_id: pid, data: { id: result.id?._serialized, group_jid: jid } });
    } catch (e) { const _dead = handleDeadSession(sanitizeId(req.body.phone_id || '') || getFirstOpenSession(), sessions.get(sanitizeId(req.body.phone_id || '') || getFirstOpenSession()), e); res.status(_dead ? 503 : 500).json({ success: false, error: _dead ? 'Sesi terputus, sedang reconnect otomatis. Silakan coba lagi dalam beberapa detik.' : e.message, reconnecting: _dead || undefined }); }
});
app.post('/api/send-image', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { number, message, image } = req.body;
        if (!number || !image) return res.status(400).json({ error: 'number and image required' });
        const media = await MessageMedia.fromUrl(image);
        const slot = await acquireSendSlot(pid);
        if (!slot.ok) return res.status(429).json({ error: slot.error, retry_after_sec: Math.ceil((slot.waitMs || 60000) / 1000) });
        const result = await withTimeout(s.client.sendMessage(numberToJid(number), media, { caption: message || '' }), 60000, 'sendMessage timeout — Chrome terlalu lambat, coba lagi');
        try { await db.query('INSERT INTO messages_log(session_id,direction,from_number,to_number,message,media_type,status,wa_msg_id) VALUES($1,$2,$3,$4,$5,$6,$7,$8)', [pid, 'out', pid, number.replace(/\D/g, ''), message || '[image]', 'image', 'sent', result.id?._serialized || '']); } catch { }
        res.json({ success: true, phone_id: pid, data: { id: result.id?._serialized } });
    } catch (e) { const _dead = handleDeadSession(pid, s, e); res.status(_dead ? 503 : 500).json({ success: false, error: _dead ? 'Sesi terputus, sedang reconnect otomatis. Silakan coba lagi dalam beberapa detik.' : e.message, reconnecting: _dead || undefined }); }
});
app.post('/api/send-location', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { number, latitude, longitude, description } = req.body;
        if (!number || latitude == null || longitude == null) return res.status(400).json({ error: 'number, latitude, and longitude required' });
        const loc = new Location(parseFloat(latitude), parseFloat(longitude), description || '');
        const slot = await acquireSendSlot(pid);
        if (!slot.ok) return res.status(429).json({ error: slot.error, retry_after_sec: Math.ceil((slot.waitMs || 60000) / 1000) });
        const result = await withTimeout(s.client.sendMessage(numberToJid(number), loc), 60000, 'sendMessage timeout — Chrome terlalu lambat, coba lagi');
        try { await db.query('INSERT INTO messages_log(session_id,direction,from_number,to_number,message,media_type,status,wa_msg_id) VALUES($1,$2,$3,$4,$5,$6,$7,$8)', [pid, 'out', pid, number.replace(/\D/g, ''), description || '[location]', 'location', 'sent', result.id?._serialized || '']); } catch { }
        res.json({ success: true, phone_id: pid, data: { id: result.id?._serialized } });
    } catch (e) { const _dead = handleDeadSession(sanitizeId(req.body.phone_id || '') || getFirstOpenSession(), sessions.get(sanitizeId(req.body.phone_id || '') || getFirstOpenSession()), e); res.status(_dead ? 503 : 500).json({ success: false, error: _dead ? 'Sesi terputus, sedang reconnect otomatis. Silakan coba lagi dalam beberapa detik.' : e.message, reconnecting: _dead || undefined }); }
});
app.post('/api/send-buttons', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { number, message, footer, buttons } = req.body;
        if (!number || !message || !buttons || !Array.isArray(buttons) || buttons.length === 0) return res.status(400).json({ error: 'number, message, and buttons[] required' });
        if (buttons.length > 3) return res.status(400).json({ error: 'Maximum 3 buttons allowed' });
        const btnList = buttons.map((b, i) => ({ id: b.id || ('btn' + (i + 1)), body: b.text || b.body || ('Button ' + (i + 1)) }));
        const btnMsg = new Buttons(message, btnList, '', footer || '');
        const slot = await acquireSendSlot(pid);
        if (!slot.ok) return res.status(429).json({ error: slot.error, retry_after_sec: Math.ceil((slot.waitMs || 60000) / 1000) });
        const result = await withTimeout(s.client.sendMessage(numberToJid(number), btnMsg), 60000, 'sendMessage timeout — Chrome terlalu lambat, coba lagi');
        try { await db.query('INSERT INTO messages_log(session_id,direction,from_number,to_number,message,media_type,status,wa_msg_id) VALUES($1,$2,$3,$4,$5,$6,$7,$8)', [pid, 'out', pid, number.replace(/\D/g, ''), message + ' [buttons]', 'buttons', 'sent', result.id?._serialized || '']); } catch { }
        res.json({ success: true, phone_id: pid, data: { id: result.id?._serialized } });
    } catch (e) { const _dead = handleDeadSession(sanitizeId(req.body.phone_id || '') || getFirstOpenSession(), sessions.get(sanitizeId(req.body.phone_id || '') || getFirstOpenSession()), e); res.status(_dead ? 503 : 500).json({ success: false, error: _dead ? 'Sesi terputus, sedang reconnect otomatis. Silakan coba lagi dalam beberapa detik.' : e.message, reconnecting: _dead || undefined }); }
});
app.post('/api/send-list', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { number, message, footer, title, buttonText, sections } = req.body;
        if (!number || !message || !buttonText || !sections || !Array.isArray(sections) || sections.length === 0) return res.status(400).json({ error: 'number, message, buttonText, and sections[] required' });
        const listMsg = new List(message, buttonText, sections, title || '', footer || '');
        const slot = await acquireSendSlot(pid);
        if (!slot.ok) return res.status(429).json({ error: slot.error, retry_after_sec: Math.ceil((slot.waitMs || 60000) / 1000) });
        const result = await withTimeout(s.client.sendMessage(numberToJid(number), listMsg), 60000, 'sendMessage timeout — Chrome terlalu lambat, coba lagi');
        try { await db.query('INSERT INTO messages_log(session_id,direction,from_number,to_number,message,media_type,status,wa_msg_id) VALUES($1,$2,$3,$4,$5,$6,$7,$8)', [pid, 'out', pid, number.replace(/\D/g, ''), message + ' [list]', 'list', 'sent', result.id?._serialized || '']); } catch { }
        res.json({ success: true, phone_id: pid, data: { id: result.id?._serialized } });
    } catch (e) { const _dead = handleDeadSession(sanitizeId(req.body.phone_id || '') || getFirstOpenSession(), sessions.get(sanitizeId(req.body.phone_id || '') || getFirstOpenSession()), e); res.status(_dead ? 503 : 500).json({ success: false, error: _dead ? 'Sesi terputus, sedang reconnect otomatis. Silakan coba lagi dalam beberapa detik.' : e.message, reconnecting: _dead || undefined }); }
});
app.post('/api/send-poll', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { number, question, options, allowMultiple } = req.body;
        if (!number || !question || !options || !Array.isArray(options) || options.length < 2) return res.status(400).json({ error: 'number, question, and options[] (min 2) required' });
        if (options.length > 12) return res.status(400).json({ error: 'Maximum 12 poll options allowed' });
        const poll = new Poll(question, options, { allowMultipleAnswers: allowMultiple !== false });
        const slot = await acquireSendSlot(pid);
        if (!slot.ok) return res.status(429).json({ error: slot.error, retry_after_sec: Math.ceil((slot.waitMs || 60000) / 1000) });
        const result = await withTimeout(s.client.sendMessage(numberToJid(number), poll), 60000, 'sendMessage timeout — Chrome terlalu lambat, coba lagi');
        try { await db.query('INSERT INTO messages_log(session_id,direction,from_number,to_number,message,media_type,status,wa_msg_id) VALUES($1,$2,$3,$4,$5,$6,$7,$8)', [pid, 'out', pid, number.replace(/\D/g, ''), question + ' [poll]', 'poll', 'sent', result.id?._serialized || '']); } catch { }
        res.json({ success: true, phone_id: pid, data: { id: result.id?._serialized } });
    } catch (e) { const _dead = handleDeadSession(sanitizeId(req.body.phone_id || '') || getFirstOpenSession(), sessions.get(sanitizeId(req.body.phone_id || '') || getFirstOpenSession()), e); res.status(_dead ? 503 : 500).json({ success: false, error: _dead ? 'Sesi terputus, sedang reconnect otomatis. Silakan coba lagi dalam beberapa detik.' : e.message, reconnecting: _dead || undefined }); }
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
    } catch (e) { const _dead = handleDeadSession(sanitizeId(req.body.phone_id || '') || getFirstOpenSession(), sessions.get(sanitizeId(req.body.phone_id || '') || getFirstOpenSession()), e); res.status(_dead ? 503 : 500).json({ success: false, error: _dead ? 'Sesi terputus, sedang reconnect otomatis. Silakan coba lagi dalam beberapa detik.' : e.message, reconnecting: _dead || undefined }); }
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
    } catch (e) { const _dead = handleDeadSession(sanitizeId(req.body.phone_id || '') || getFirstOpenSession(), sessions.get(sanitizeId(req.body.phone_id || '') || getFirstOpenSession()), e); res.status(_dead ? 503 : 500).json({ success: false, error: _dead ? 'Sesi terputus, sedang reconnect otomatis. Silakan coba lagi dalam beberapa detik.' : e.message, reconnecting: _dead || undefined }); }
});
app.post('/api/leave-group', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { group_id } = req.body;
        if (!group_id) return res.status(400).json({ error: 'group_id required' });
        const jid = resolveGroupJid(group_id, s.groupCache);
        if (!jid) return res.status(404).json({ error: 'Group not found: ' + group_id });
        const chat = await s.client.getChatById(jid);
        await chat.leave();
        s.groupCache.delete(jid);
        res.json({ success: true, phone_id: pid, left_group: jid });
    } catch (e) { const _dead = handleDeadSession(sanitizeId(req.body.phone_id || '') || getFirstOpenSession(), sessions.get(sanitizeId(req.body.phone_id || '') || getFirstOpenSession()), e); res.status(_dead ? 503 : 500).json({ success: false, error: _dead ? 'Sesi terputus, sedang reconnect otomatis. Silakan coba lagi dalam beberapa detik.' : e.message, reconnecting: _dead || undefined }); }
});
app.post('/api/create-group', apiAuth, async (req, res) => {
    try {
        const pid = sanitizeId(req.body.phone_id || '') || getFirstOpenSession();
        const s = sessions.get(pid);
        if (!s || s.status !== 'open') return res.status(503).json({ error: 'Session not connected', phone_id: pid });
        const { name, participants } = req.body;
        if (!name) return res.status(400).json({ error: 'Group name required' });
        const nums = (Array.isArray(participants) ? participants : (participants || '').split(',')).map(n => String(n).trim()).filter(Boolean).map(n => numberToJid(n));
        if (!nums.length) return res.status(400).json({ error: 'At least one participant required' });
        const result = await s.client.createGroup(name, nums);
        await refreshGroupCacheForSession(pid);
        res.json({ success: true, phone_id: pid, data: { gid: result.gid?._serialized || '', title: result.title || name } });
    } catch (e) { res.status(500).json({ success: false, error: e.message }); }
});
app.get('/api/groups', apiAuth, (req, res) => {
    const pid = sanitizeId(req.query.phone_id || '') || getFirstOpenSession();
    const s = sessions.get(pid);
    if (!s) return res.status(404).json({ error: 'Session not found' });
    const groups = [];
    for (const [jid, meta] of s.groupCache) groups.push({ jid, name: meta.subject, participants: meta.participants?.length || 0 });
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

// ─── PROACTIVE MONITORING AGENT ───────────────────────────────────────────────
const _agentLog = [];       // ring buffer, max 200 entries
const _agentState = { lastRun: null, checks: 0, actions: 0, lastDailyReport: null, lastEscalation: null, escalationCount: 0 };
const ESCALATION_WA = '085719195627';
const ESCALATION_EMAIL = 'me@jodyaryono.id';
const _escalationCooldown = new Map(); // id → timestamp, prevent spam

function agentLog(msg) {
    const ts = new Date().toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' });
    const entry = '[' + ts + '] ' + msg;
    console.log('[Agent] ' + msg);
    _agentLog.push(entry);
    if (_agentLog.length > 200) _agentLog.shift();
}

async function sendAgentWA(text, toNumber) {
    const target = toNumber || NOTIFY_WA;
    try {
        for (const [, s] of sessions) {
            if (s.status === 'open' && s.client) {
                await s.client.sendMessage(numberToJid(target), text);
                return true;
            }
        }
    } catch (e) { agentLog('WA send error: ' + e.message); }
    return false;
}

async function escalateToHuman(subject, details) {
    const cooldownKey = subject.replace(/[^a-zA-Z]/g, '').slice(0, 30);
    const lastTime = _escalationCooldown.get(cooldownKey) || 0;
    // Cooldown: don't escalate same issue within 15 min
    if (Date.now() - lastTime < 15 * 60 * 1000) return;
    _escalationCooldown.set(cooldownKey, Date.now());
    _agentState.lastEscalation = new Date();
    _agentState.escalationCount++;
    const timeStr = new Date().toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' });

    agentLog('ESCALATION: ' + subject);

    // WA to human
    const waMsg = '🚨 *ESCALATION — Perlu Tindakan Manual*\n\n'
        + '📋 ' + subject + '\n'
        + '🕐 ' + timeStr + ' WIB\n\n'
        + details + '\n\n'
        + '🔗 Dashboard: https://integrasi-wa.jodyaryono.id/dashboard\n'
        + '📊 Agent: https://integrasi-wa.jodyaryono.id/api/agent/status?token=' + AUTH_TOKEN;
    const waSent = await sendAgentWA(waMsg, ESCALATION_WA);
    if (!waSent) agentLog('Escalation WA failed — no open session available');

    // Email to human
    try {
        await mailer.sendMail({
            from: 'WA Agent <' + SMTP_USER + '>',
            to: ESCALATION_EMAIL,
            subject: '🚨 WA Gateway Escalation: ' + subject,
            html: '<div style="font-family:sans-serif;max-width:600px">'
                + '<h2 style="color:#dc2626">🚨 Escalation — Perlu Tindakan Manual</h2>'
                + '<p style="font-size:1.1rem;font-weight:600">' + subject + '</p>'
                + '<p style="color:#6b7280">Waktu: ' + timeStr + ' WIB</p>'
                + '<pre style="background:#f3f4f6;padding:12px;border-radius:6px;white-space:pre-wrap">' + details.replace(/</g, '&lt;') + '</pre>'
                + '<p style="margin-top:20px"><a href="https://integrasi-wa.jodyaryono.id/dashboard" style="background:#dc2626;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:600">Buka Dashboard</a></p>'
                + '</div>',
        });
        agentLog('Escalation email sent to ' + ESCALATION_EMAIL);
    } catch (e) { agentLog('Escalation email error: ' + e.message); }
}

async function runMonitoringAgent() {
    _agentState.lastRun = new Date();
    _agentState.checks++;
    const now = Date.now();
    const actions = [];

    // 1. Session status audit
    const total = sessions.size;
    let open = 0, connecting = 0, disconnected = 0, stuckConnecting = 0, orphaned = 0;

    for (const [id, sess] of sessions.entries()) {
        if (sess.status === 'open') { open++; continue; }
        if (sess.status === 'connecting') {
            connecting++;
            // Skip sessions that are actively showing QR (waiting for user scan — this is expected)
            if (sess._qrCount > 0) continue;
            // Stuck in "connecting" > 8 min WITHOUT QR = likely Chrome zombied
            // (post-init ready timer handles 5min case, agent is last resort at 8min)
            const since = sess._connectingSince || now;
            if (now - since > 8 * 60 * 1000 && !sess._removing && !sess._qrTimeout) {
                stuckConnecting++;
                agentLog('STUCK: ' + id + ' connecting for ' + Math.round((now - since) / 60000) + 'm — forcing restart');
                await safeDestroy(sess.client, id);
                sess.client = null;
                sess.status = 'disconnected';
                sess.connectedAt = null;
                sess._connectingSince = null;
                if (sess._reconnectTimer) { clearTimeout(sess._reconnectTimer); sess._reconnectTimer = null; }
                sess._failCount = 0;
                startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled);
                actions.push('Restarted stuck session: ' + id);
            }
            continue;
        }
        if (sess.status === 'disconnected') {
            disconnected++;
            // Orphaned: disconnected, no reconnect timer, not being removed, not user-disconnected
            // BUT skip sessions without valid auth — they need manual QR pairing from dashboard
            if (!sess._reconnectTimer && !sess._removing && !sess._userDisconnecting && !_shuttingDown && hasValidAuth(id)) {
                orphaned++;
                agentLog('ORPHAN: ' + id + ' disconnected with no reconnect scheduled — auto-recovering');
                sess._failCount = 0;
                startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled);
                actions.push('Recovered orphaned session: ' + id);
            }
        }
    }

    // 2. RAM pressure check
    const totalMem = os.totalmem();
    const freeMem = os.freemem();
    const usedPercent = ((totalMem - freeMem) / totalMem * 100);
    if (usedPercent > 90) {
        agentLog('RAM CRITICAL: ' + usedPercent.toFixed(1) + '% — killing QR-waiting sessions');
        for (const [id, sess] of sessions.entries()) {
            if (sess.status === 'connecting' && sess._qrCount > 0 && sess.client) {
                agentLog('RAM: Killing QR session ' + id + ' to free memory');
                sess._qrTimeout = true;
                sess.status = 'disconnected';
                sess.qrCode = null; sess.qrDataUrl = null; sess._qrCount = 0;
                try { await sess.client.destroy(); } catch { }
                sess.client = null;
                actions.push('Killed QR session for RAM: ' + id);
            }
        }
    }

    // 3. Chrome zombie cleanup — kill orphaned Chrome processes not owned by any session
    try {
        const chromeCount = parseInt(execSync('pgrep -c chrome || echo 0', { timeout: 3000, encoding: 'utf8' }).trim()) || 0;
        let activeClients = 0;
        const activeDataDirs = new Set();
        for (const [id, s] of sessions) {
            if (s.client) { activeClients++; activeDataDirs.add(path.resolve('./auth_info', 'session-' + id)); }
        }
        if (chromeCount > (activeClients * 4 + 5) && chromeCount > 10) {
            agentLog('CHROME-HIGH: ' + chromeCount + ' Chrome procs, ' + activeClients + ' active clients — scanning for zombies');
            // Find all Chrome PIDs with their data-dir
            try {
                const psOut = execSync('ps aux | grep "user-data-dir=.*auth_info" | grep -v grep', { timeout: 5000, encoding: 'utf8' });
                const zombiePids = [];
                for (const line of psOut.trim().split('\n')) {
                    if (!line.trim()) continue;
                    const pidMatch = line.match(/^\S+\s+(\d+)/);
                    const dirMatch = line.match(/user-data-dir=([^\s]+)/);
                    if (pidMatch && dirMatch) {
                        const pid = pidMatch[1], dir = dirMatch[1];
                        if (!activeDataDirs.has(dir)) zombiePids.push({ pid, dir });
                    }
                }
                if (zombiePids.length > 0) {
                    agentLog('ZOMBIE-KILL: Killing ' + zombiePids.length + ' orphaned Chrome processes');
                    for (const z of zombiePids) {
                        try { execSync('kill -9 ' + z.pid, { timeout: 2000 }); } catch { }
                    }
                }
            } catch { }
        }
    } catch { }

    // 4. Log summary
    if (actions.length > 0) {
        _agentState.actions += actions.length;
        agentLog('Cycle #' + _agentState.checks + ': ' + open + ' open, ' + connecting + ' connecting, ' + disconnected + ' disconnected — ' + actions.length + ' actions taken');
    }

    // 5. ESCALATION — conditions that need human attention
    // 5a. ALL sessions down — only escalate after 3 consecutive cycles (3 min grace period)
    if (open === 0 && total > 0) {
        _agentState._allDownCount = (_agentState._allDownCount || 0) + 1;
        if (_agentState._allDownCount >= 3) {
            await escalateToHuman(
                'Semua session WhatsApp DOWN (3+ menit)',
                'Tidak ada session yang connected selama 3+ menit.\n'
                + 'Total sessions: ' + total + '\n'
                + 'Connecting: ' + connecting + ', Disconnected: ' + disconnected + '\n'
                + 'Agent sudah coba auto-recover tapi belum berhasil.\n'
                + 'Kemungkinan: server resource penuh, WA ban, atau perlu re-pair semua nomor.'
            );
        } else {
            agentLog('ALL-DOWN: 0 open sessions (cycle ' + _agentState._allDownCount + '/3 before escalation)');
        }
    } else {
        _agentState._allDownCount = 0;
    }
    // 5b. Session with valid auth keeps failing (>= 5 consecutive fails) — likely WA ban or phone issue
    for (const [id, sess] of sessions.entries()) {
        if ((sess._failCount || 0) >= 5 && sess.status !== 'open' && !sess._removing) {
            await escalateToHuman(
                'Session ' + (sess.label || id) + ' gagal 5x berturut-turut',
                'Session: ' + (sess.label || id) + ' (' + id + ')\n'
                + 'Fail count: ' + sess._failCount + '\n'
                + 'Status: ' + sess.status + '\n'
                + 'Kemungkinan: nomor di-ban WA, HP offline, atau perlu re-scan QR.'
            );
        }
    }
    // 5c. RAM critical (>90%) AND already killed QR sessions but still high
    if (usedPercent > 90) {
        await escalateToHuman(
            'RAM kritis: ' + usedPercent.toFixed(1) + '%',
            'RAM usage: ' + usedPercent.toFixed(1) + '%\n'
            + 'Sessions aktif: ' + open + ' open, ' + connecting + ' connecting\n'
            + 'Agent sudah kill QR sessions tapi RAM masih tinggi.\n'
            + 'Perlu: restart server atau kurangi jumlah session aktif.'
        );
    }
    // 5d. Paired session disconnected > 10 min — something seriously wrong
    for (const [id, sess] of sessions.entries()) {
        if (sess.status === 'disconnected' && !sess._removing && !sess._userDisconnecting) {
            const pairedFile = path.join('./auth_info', 'session-' + id, '.paired');
            if (fs.existsSync(pairedFile)) {
                // Was paired before but now disconnected for a while
                if ((sess._failCount || 0) >= 3) {
                    await escalateToHuman(
                        'Session ' + (sess.label || id) + ' terputus terus-menerus',
                        'Session: ' + (sess.label || id) + ' (' + id + ')\n'
                        + 'Status: disconnected, sudah ' + (sess._failCount || 0) + 'x coba reconnect\n'
                        + 'Session ini pernah connected sebelumnya.\n'
                        + 'Perlu: cek HP apakah online, atau re-scan QR dari dashboard.'
                    );
                }
            }
        }
    }

    // 6. Daily report at 8:00 AM Jakarta time + DB retention cleanup
    const jakartaHour = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' })).getHours();
    const jakartaMin = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' })).getMinutes();
    const today = new Date().toISOString().slice(0, 10);
    if (jakartaHour === 8 && jakartaMin < 2 && _agentState.lastDailyReport !== today) {
        _agentState.lastDailyReport = today;
        // Daily DB retention: prune messages_log older than 30 days
        try {
            const pruned = await db.query(`DELETE FROM messages_log WHERE created_at < NOW() - INTERVAL '30 days'`);
            if (pruned.rowCount > 0) agentLog('DB retention: pruned ' + pruned.rowCount + ' old messages_log rows');
            const prunedBroadcast = await db.query(`DELETE FROM broadcast_jobs WHERE status='done' AND created_at < NOW() - INTERVAL '30 days'`);
            if (prunedBroadcast.rowCount > 0) agentLog('DB retention: pruned ' + prunedBroadcast.rowCount + ' old broadcast_jobs rows');
        } catch (e) { agentLog('DB retention error: ' + e.message); }
        let allOpen = true;
        for (const [, sess] of sessions.entries()) { if (sess.status !== 'open') { allOpen = false; break; } }
        // Only send daily report if there are problems — no spam when everything is fine
        if (!allOpen || _agentState.escalationCount > 0 || _agentState.actions > 10 || usedPercent > 80) {
            const lines = ['📊 *Daily WA Gateway Report*', '📅 ' + today, ''];
            for (const [id, sess] of sessions.entries()) {
                const icon = sess.status === 'open' ? '✅' : (sess.status === 'connecting' ? '🔄' : '❌');
                const upInfo = sess.connectedAt ? 'up ' + Math.round((now - new Date(sess.connectedAt).getTime()) / 3600000) + ' jam' : 'not connected';
                lines.push(icon + ' *' + (sess.label || id) + '* (' + id + '): ' + sess.status + ' — ' + upInfo);
            }
            lines.push('', '💾 RAM: ' + usedPercent.toFixed(0) + '% used');
            lines.push('⚙️ Uptime: ' + Math.round(process.uptime() / 3600) + ' jam');
            lines.push('🔧 Agent checks: ' + _agentState.checks + ', actions: ' + _agentState.actions);
            lines.push('🚨 Escalations: ' + _agentState.escalationCount);
            lines.push('', '⚠️ Ada session yg perlu perhatian.');
            const reportText = lines.join('\n');
            await sendAgentWA(reportText, NOTIFY_WA);
            await sendAgentWA(reportText, ESCALATION_WA);
            agentLog('Daily report sent (ada masalah)');
        } else {
            agentLog('Daily check: semua aman, skip report');
        }
        // Reset daily counters
        _agentState.actions = 0;
        _agentState.escalationCount = 0;
        _escalationCooldown.clear();
    }
}

// Agent API endpoint
app.get('/api/agent/status', apiAuth, (req, res) => {
    const sessStatus = {};
    for (const [id, sess] of sessions.entries()) {
        sessStatus[id] = {
            label: sess.label,
            status: sess.status,
            hasClient: !!sess.client,
            hasReconnectTimer: !!sess._reconnectTimer,
            failCount: sess._failCount || 0,
            hbFails: sess._hbFails || 0,
            qrCount: sess._qrCount || 0,
            connectedAt: sess.connectedAt,
            connectingSince: sess._connectingSince,
        };
    }
    res.json({
        agent: _agentState,
        sessions: sessStatus,
        ram: { total: (os.totalmem() / 1024 / 1024).toFixed(0) + 'MB', used: ((os.totalmem() - os.freemem()) / 1024 / 1024).toFixed(0) + 'MB', percent: ((os.totalmem() - os.freemem()) / os.totalmem() * 100).toFixed(1) },
        recentLogs: _agentLog.slice(-20),
    });
});

app.get('/api/agent/logs', apiAuth, (req, res) => {
    const limit = Math.min(parseInt(req.query.limit || '50'), 200);
    res.json({ logs: _agentLog.slice(-limit) });
});

// ─── START ────────────────────────────────────────────────────────────────────
async function main() {
    // Cleanup stale Chrome processes from previous run
    try { execSync('pkill -f "auth_info/session-" || true', { timeout: 5000 }); } catch { }
    await initDb();
    app.listen(PORT, () => {
        console.log('[Gateway] Port ' + PORT);
        console.log('[Gateway] http://localhost:' + PORT + '/dashboard');
    });
    await loadSessionsFromDb();

    // ─── KEEP-ALIVE HEARTBEAT ──────────────────
    // Every 3 minutes: ping each 'open' session.
    // If Chrome died silently (OOM kill, no 'disconnected' event), detect + reconnect.
    // Tolerates up to 3 consecutive slow responses before declaring dead (avoid false positives on low-RAM VPS).
    setInterval(async () => {
        for (const [id, sess] of sessions.entries()) {
            if (sess.status !== 'open' || !sess.client || sess._reconnectTimer || sess._removing) continue;
            try {
                const state = await Promise.race([
                    sess.client.getState(),
                    new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 45000)),
                ]);
                if (!state) throw new Error('null state');
                // Heartbeat OK — reset consecutive fail counter
                sess._hbFails = 0;
            } catch (e) {
                sess._hbFails = (sess._hbFails || 0) + 1;
                if (sess._hbFails < 3) {
                    console.log('[Heartbeat][' + id + '] Slow (' + e.message + '), attempt ' + sess._hbFails + '/3 — will retry next cycle');
                    continue;
                }
                console.log('[Heartbeat][' + id + '] Dead after ' + sess._hbFails + ' fails (' + e.message + '), reconnecting...');
                sess._hbFails = 0;
                sess.status = 'disconnected';
                sess.connectedAt = null;
                try { await Promise.race([sess.client.destroy(), new Promise(r => setTimeout(r, 8000))]); } catch { }
                sess.client = null;
                sess._failCount = (sess._failCount || 0) + 1;
                const delay = sess._failCount >= 5 ? 5 * 60 * 1000 : 30000;
                sess._reconnectTimer = setTimeout(() => { sess._reconnectTimer = null; startSession(id, sess.label, sess.apiToken, sess.webhookUrl, sess.webhookEnabled); }, delay);
            }
        }
    }, 3 * 60 * 1000);

    // ─── PROACTIVE MONITORING AGENT ──────────────
    // Runs every 60s: detect stuck sessions, orphans, zombies, RAM pressure.
    // Daily report at 8 AM Jakarta time.
    setTimeout(() => {
        agentLog('Agent started — monitoring ' + sessions.size + ' sessions');
        setInterval(() => runMonitoringAgent().catch(e => agentLog('Error: ' + e.message)), 60 * 1000);
    }, 90 * 1000); // delay 90s after startup to let sessions settle
}
main().catch(e => { console.error('[Fatal]', e); process.exit(1); });
