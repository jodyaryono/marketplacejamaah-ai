const fs2 = require('fs');
const orig = fs2.readFileSync('/var/www/integrasi-wa.jodyaryono.id/index.js', 'utf8');

// 1) Add MEDIA_BASE_URL constant after the existing WEBHOOK vars
const insertAfter = 'const WEBHOOK_ENABLED';
const idx1 = orig.indexOf(insertAfter);
const eol1 = orig.indexOf('\n', idx1);
const constBlock = "\nconst MEDIA_BASE_URL = 'https://integrasi-wa.jodyaryono.id/uploads/';\nconst UPLOADS_DIR = path.resolve('./uploads');\nif (!fs.existsSync(UPLOADS_DIR)) fs.mkdirSync(UPLOADS_DIR, { recursive: true });\n";

let patched = orig.slice(0, eol1 + 1) + constBlock + orig.slice(eol1 + 1);

// 2) Add media download logic before '    // Per-session webhook'
const mediaBlock = '\n'
    + '    // ── Download & save media ───────────────────────────────────────\n'
    + '    let mediaUrl = null;\n'
    + '    if (msg.hasMedia) {\n'
    + '        try {\n'
    + '            const media = await msg.downloadMedia();\n'
    + '            if (media && media.data) {\n'
    + "                const extMap = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp', 'video/mp4': 'mp4', 'video/3gpp': '3gp', 'audio/ogg': 'ogg', 'audio/mpeg': 'mp3', 'application/pdf': 'pdf' };\n"
    + "                const ext = extMap[media.mimetype] || (media.mimetype ? media.mimetype.split('/')[1] : 'bin');\n"
    + "                const filename = 'wa_' + Date.now() + '_' + randomBytes(4).toString('hex') + '.' + ext;\n"
    + '                const filePath = path.join(UPLOADS_DIR, filename);\n'
    + "                fs.writeFileSync(filePath, Buffer.from(media.data, 'base64'));\n"
    + '                mediaUrl = MEDIA_BASE_URL + filename;\n'
    + "                console.log('[Media][' + phoneId + '] saved ' + filename + ' (' + media.mimetype + ')');\n"
    + '            }\n'
    + '        } catch (e) {\n'
    + "            console.error('[Media][' + phoneId + '] download failed:', e.message);\n"
    + '        }\n'
    + '    }\n';

const marker = '    // Per-session webhook';
patched = patched.replace(marker, mediaBlock + '\n' + marker);

// 3) Add media_url to the webhook payload - before _key
patched = patched.replace(
    '            _key: { remoteJid:',
    '            ...(mediaUrl ? { media_url: mediaUrl } : {}),\n            _key: { remoteJid:'
);

fs2.writeFileSync('/var/www/integrasi-wa.jodyaryono.id/index.js', patched);
console.log('Patch applied successfully');
