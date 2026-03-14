# Integrasi WA — API Reference

> WhatsApp Gateway Multi-Session API  
> Base URL: `https://integrasi-wa.jodyaryono.id`

---

## Authentication

### API Auth (`/api/*` routes)

All `/api/*` endpoints require a Bearer token in the `Authorization` header:

```
Authorization: Bearer {token}
```

**Valid tokens:**

- **Global token**: `AUTH_TOKEN` environment variable
- **Per-session token**: Each session has a unique `apiToken` (auto-generated, retrievable from dashboard)

Fallback: token can also be passed as `token` query param or body field.

### Web Auth (`/web/*` routes)

Browser session-based login. Requires `POST /login` with `username` + `password`.

- Default: `admin` / `admin123` (configurable via `ADMIN_USER`, `ADMIN_PASS` env vars)
- Brute force protection: 5 failed attempts → 5-minute lockout per IP

---

## Rate Limiting

Per-session send throttle via `acquireSendSlot(phoneId)`:

| Setting                 | Default | Env Var             |
| ----------------------- | ------- | ------------------- |
| Min delay between sends | 5000ms  | `SEND_DELAY_MS`     |
| Max messages per hour   | 150     | `SEND_HOURLY_LIMIT` |

Rate-limited endpoints return HTTP `429`:

```json
{
    "error": "Batas kirim 150 pesan/jam tercapai. Coba lagi dalam X menit.",
    "retry_after_sec": 300
}
```

---

## Anti-Ban

Human-behaviour simulation is applied automatically on every send to reduce the risk of WhatsApp flagging the session.

| Feature           | Default  | Env Var               | Description                                    |
| ----------------- | -------- | --------------------- | ---------------------------------------------- |
| Typing simulation | `true`   | `ANTI_BAN_TYPING`     | Set `false` to disable                         |
| Random jitter     | 0–2000ms | `ANTI_BAN_JITTER_MS`  | Max extra random delay added per send          |
| Typing speed      | 15 cps   | `ANTI_BAN_TYPING_CPS` | Chars/sec used to calculate composing duration |

### Behaviour

1. **Jitter** — after the minimum inter-send delay, an additional random sleep of `0..ANTI_BAN_JITTER_MS` ms is applied so messages never arrive at machine-perfect intervals.
2. **Typing simulation** — for text messages (`/api/send`, `/api/sendGroup`, `/api/send-buttons`), the gateway sets the session's presence to `composing` for a duration proportional to the message length before sending, then sets it to `paused`. This mimics a human typing.

### Response field

All send endpoints include an `anti_ban` object in their success response:

```json
{
    "success": true,
    "phone_id": "6281234567890",
    "data": { "id": "WAMID_xxx" },
    "anti_ban": {
        "simulated_typing": true,
        "jitter_ms": 1243
    }
}
```

| Field              | Type    | Description                                      |
| ------------------ | ------- | ------------------------------------------------ |
| `simulated_typing` | boolean | Whether typing simulation was performed          |
| `jitter_ms`        | number  | Actual random delay added in ms (this send only) |

---

## Sending Messages

### POST /api/send

Send text message to individual number.

**Rate limited:** Yes

```json
// Request
{
  "phone_id": "6281234567890",  // optional, uses first open session if omitted
  "number": "6281234567891",
  "message": "Hello!"
}

// Response 200
{ "success": true, "phone_id": "6281234567890", "data": { "id": "WAMID_xxx" } }
```

### POST /api/sendGroup

Send text message to a group.

**Rate limited:** Yes

```json
// Request
{
  "phone_id": "6281234567890",  // optional
  "group": "120363xxx@g.us",    // JID or partial group name (case-insensitive)
  "message": "Hello group!"
}

// Response 200
{ "success": true, "phone_id": "6281234567890", "data": { "id": "WAMID_xxx", "group_jid": "120363xxx@g.us" } }
```

### POST /api/send-image

Send image from URL with optional caption.

**Rate limited:** Yes

```json
// Request
{
  "phone_id": "6281234567890",  // optional
  "number": "6281234567891",
  "image": "https://example.com/photo.jpg",
  "message": "Check this out"   // optional caption
}

// Response 200
{ "success": true, "phone_id": "6281234567890", "data": { "id": "WAMID_xxx" } }
```

### POST /api/send-location

Send location pin.

**Rate limited:** Yes

```json
// Request
{
  "phone_id": "6281234567890",  // optional
  "number": "6281234567891",
  "latitude": -6.2088,
  "longitude": 106.8456,
  "description": "Jakarta"      // optional
}

// Response 200
{ "success": true, "phone_id": "6281234567890", "data": { "id": "WAMID_xxx" } }
```

### POST /api/send-buttons

Send interactive button message (max 3 buttons).

**Rate limited:** Yes

```json
// Request
{
  "phone_id": "6281234567890",  // optional
  "number": "6281234567891",
  "message": "Choose an option:",
  "footer": "Powered by WA Gateway",  // optional
  "buttons": [
    { "id": "btn1", "text": "Option A" },
    { "id": "btn2", "text": "Option B" }
  ]
}

// Response 200
{ "success": true, "phone_id": "6281234567890", "data": { "id": "WAMID_xxx" } }
```

### POST /api/send-list

Send interactive list message with sections.

**Rate limited:** Yes

```json
// Request
{
    "phone_id": "6281234567890", // optional
    "number": "6281234567891",
    "message": "Menu:",
    "buttonText": "View Menu",
    "title": "Main Menu", // optional
    "footer": "Select one", // optional
    "sections": [
        {
            "title": "Category A",
            "rows": [
                { "id": "row1", "title": "Item 1", "description": "Desc 1" }
            ]
        }
    ]
}
```

### POST /api/send-poll

Send poll/survey (2–12 options).

**Rate limited:** Yes

```json
// Request
{
    "phone_id": "6281234567890", // optional
    "number": "6281234567891",
    "question": "Favorite color?",
    "options": ["Red", "Blue", "Green"],
    "allowMultiple": false // optional, default false
}
```

---

## Group Management

### GET /api/groups

List all cached groups for a session.

```
GET /api/groups?phone_id=6281234567890
```

```json
// Response 200
{
    "phone_id": "6281234567890",
    "groups": [
        { "jid": "120363xxx@g.us", "name": "My Group", "participants": 25 }
    ]
}
```

### GET /api/group-members

Get group members with roles.

```
GET /api/group-members?phone_id=6281234567890&group_id=120363xxx@g.us
```

```json
// Response 200
{
    "phone_id": "6281234567890",
    "group_id": "120363xxx@g.us",
    "total": 25,
    "members": [
        { "number": "6281234567890", "role": "superadmin" },
        { "number": "6281234567891", "role": "admin" },
        { "number": "6281234567892", "role": "member" }
    ]
}
```

### POST /api/create-group

Create a new group.

```json
// Request
{
  "phone_id": "6281234567890",  // optional
  "name": "New Group",
  "participants": ["6281234567891", "6281234567892"]  // or comma-separated string
}

// Response 200
{ "success": true, "phone_id": "6281234567890", "data": { "gid": "120363xxx@g.us", "title": "New Group" } }
```

### POST /api/refresh-groups

Force refresh group cache from WhatsApp (ignores 5-min debounce).

```json
// Request
{ "phone_id": "6281234567890" }  // optional

// Response 200
{ "success": true, "phone_id": "6281234567890", "groups": [...] }
```

### POST /api/kick

Remove member from group (bot must be admin).

```json
// Request
{
  "phone_id": "6281234567890",  // optional
  "group_id": "120363xxx@g.us", // JID or partial name
  "member": "6281234567891"
}

// Response 200
{ "success": true, "data": {...} }
```

### POST /api/leave-group

Leave a group.

```json
// Request
{
  "phone_id": "6281234567890",  // optional
  "group_id": "120363xxx@g.us"
}

// Response 200
{ "success": true, "phone_id": "6281234567890", "left_group": "120363xxx@g.us" }
```

### POST /api/approve-membership

Approve a pending group join request (bot must be admin).

```json
// Request
{
  "phone_id": "6281234567890",  // optional
  "group_id": "120363xxx@g.us",
  "requester": "6281234567891"
}

// Response 200
{ "success": true, "phone_id": "6281234567890", "data": {...} }
```

### POST /api/reject-membership

Reject a pending group join request (bot must be admin).

```json
// Request
{
  "phone_id": "6281234567890",  // optional
  "group_id": "120363xxx@g.us",
  "requester": "6281234567891"
}

// Response 200
{ "success": true, "phone_id": "6281234567890", "data": {...} }
```

---

## Message Management

### POST /api/delete

Delete a message from chat.

```json
// Request — by key
{
  "phone_id": "6281234567890",
  "key": { "remoteJid": "120363xxx@g.us", "id": "WAMID_xxx", "fromMe": false, "participant": "6281234567891@c.us" }
}

// Request — by group_id + message_id
{
  "phone_id": "6281234567890",
  "group_id": "120363xxx@g.us",
  "message_id": "WAMID_xxx",
  "participant": "6281234567891"
}
```

---

## Status & Monitoring

### GET /api/status

Get all sessions status.

```json
// Response 200
{
    "sessions": {
        "6281234567890": {
            "label": "Main",
            "status": "open",
            "groups_cached": 15
        }
    },
    "uptime": 86400
}
```

### GET /api/qr/:id

Get QR code for session.

```json
// Response 200
{
    "status": "waiting", // "already_connected" | "waiting" | "connecting"
    "qr": "data:image/png;base64,..." // null if not ready
}
```

### GET /api/debug/:id

Debug Chrome/WhatsApp state for session.

```json
// Response 200
{
    "phone_id": "6281234567890",
    "status": "open",
    "results": {
        "pageTitle": "WhatsApp",
        "getState": "CONNECTED",
        "waStore": true,
        "isRegistered": true
    }
}
```

### GET /api/agent/status

Get proactive monitoring agent status.

```json
// Response 200
{
    "agent": {
        "lastRun": "...",
        "checks": 1234,
        "actions": 56,
        "lastDailyReport": "...",
        "escalationCount": 2
    },
    "sessions": {
        "6281234567890": {
            "label": "Main",
            "status": "open",
            "failCount": 0,
            "hbFails": 0
        }
    },
    "ram": { "total": "4096MB", "used": "2048MB", "percent": "50%" },
    "recentLogs": ["..."]
}
```

### GET /api/agent/logs

Get monitoring agent logs.

```
GET /api/agent/logs?limit=50
```

---

## Webhook Events

When webhook is enabled for a session, the gateway sends POST requests to the configured URL.

### Incoming Message

Fired when a message is received (not from bot itself).

```json
{
    "phone_id": "6281234567890",
    "message_id": "WAMID_xxx",
    "message": "Hello!",
    "type": "text",
    "timestamp": 1710316800,
    "sender": "6281234567891",
    "sender_name": "John",
    "from": "6281234567891",
    "pushname": "John",
    "_key": {
        "remoteJid": "6281234567891@c.us",
        "id": "WAMID_xxx",
        "fromMe": false
    }
}
```

**If from group**, additional fields:

```json
{
    "group_id": "120363xxx@g.us",
    "from_group": "120363xxx@g.us",
    "group_name": "My Group",
    "_key": {
        "remoteJid": "120363xxx@g.us",
        "participant": "6281234567891@c.us"
    }
}
```

**If location message**, additional field:

```json
{
    "location": {
        "latitude": -6.2088,
        "longitude": 106.8456,
        "description": "Jakarta",
        "url": "https://..."
    }
}
```

### group_join

Fired when a user joins a group.

```json
{
    "phone_id": "6281234567890",
    "type": "group_join",
    "group_id": "120363xxx@g.us",
    "group_name": "My Group",
    "who": ["6281234567891@c.us"],
    "invitedBy": "6281234567892@c.us",
    "timestamp": 1710316800
}
```

### group_leave

Fired when a user leaves or is removed from a group.

```json
{
    "phone_id": "6281234567890",
    "type": "group_leave",
    "group_id": "120363xxx@g.us",
    "group_name": "My Group",
    "who": ["6281234567891@c.us"],
    "timestamp": 1710316800
}
```

### group_membership_request

Fired when a user requests to join a group (pending approval).

```json
{
    "phone_id": "6281234567890",
    "type": "group_membership_request",
    "group_id": "120363xxx@g.us",
    "group_name": "My Group",
    "requester": "6281234567891@c.us",
    "requester_ids": ["6281234567891@c.us"],
    "timestamp": 1710316800
}
```

**To approve/reject**, call `POST /api/approve-membership` or `POST /api/reject-membership`.

---

## Session Management (Web API)

### GET /web/sessions

List all sessions.

```json
{
    "sessions": [
        {
            "phone_id": "6281234567890",
            "label": "Main",
            "status": "open",
            "groups": 15,
            "api_token": "abc123...",
            "webhook_url": "https://...",
            "webhook_enabled": true,
            "created_at": "2026-01-01T00:00:00Z",
            "connected_at": "2026-03-13T00:00:00Z",
            "paired": true
        }
    ]
}
```

### POST /web/session/add

Add new session.

```json
// Request
{ "phoneId": "6281234567890", "label": "Main Phone" }

// Response 200
{ "success": true, "phone_id": "6281234567890" }
```

### POST /web/session/:id/delete

Delete session (kills Chrome, removes auth files).

### POST /web/session/:id/disconnect

Disconnect session (logout from WhatsApp, removes auth — requires QR re-scan).

### POST /web/session/:id/reconnect

Force reconnect (preserves auth).

### POST /web/session/:id/restart

Safe restart (preserves Chrome if possible).

### POST /web/session/:id/pairing-code

Get pairing code for linking without QR scan.

```json
// Request
{ "phone": "6281234567890" }

// Response 200
{ "code": "XXX-XXX-XXX" }
```

### POST /web/session/:id/webhook

Configure webhook for session.

```json
// Request
{ "webhook_url": "https://myapi.com/webhook", "webhook_enabled": true }

// Response 200
{ "ok": true }
```

### GET /web/session/:id/health

Detailed health info for one session.

### GET /web/session/:id/history

Message history (paginated, 20/page).

```
GET /web/session/:id/history?page=1&dir=in
```

### GET /web/session/:id/get-token

Get session's API token (for use in `/api/*` calls).

```json
// Response 200
{ "phone_id": "6281234567890", "api_token": "abc123..." }
```

### GET /web/session/:id/ai-instructions

Get AI agent prompt with API instructions for this session.

---

## Web Dashboard Internal APIs

> These endpoints are used by the dashboard UI. All require browser session login (`requireLogin`).

### GET /web/qr/:id

Get QR code image page for session (HTML).

### POST /web/webhook/test

Test webhook URL reachability.

```json
// Request
{ "url": "https://myapi.com/webhook" }

// Response 200
{ "ok": true, "status": 200 }
```

### POST /web/broadcast/save

Save and execute a broadcast job.

```json
// Request
{
    "session_id": "6281234567890",
    "message": "Hello everyone!",
    "recipients": ["6281234567891", "6281234567892"],
    "delay_ms": 3000
}
```

### POST /web/contact/add

Add a contact to the contact book.

```json
// Request
{ "name": "John Doe", "phone": "6281234567890", "notes": "Customer" }
```

### POST /web/contact/:id/edit

Edit an existing contact.

```json
// Request
{ "name": "John Doe", "phone": "6281234567890", "notes": "Updated notes" }
```

### POST /web/contact/:id/delete

Delete a contact.

### POST /web/autoreply/add

Add an auto-reply rule.

```json
// Request
{
    "keyword": "halo",
    "reply": "Halo juga! Ada yang bisa dibantu?",
    "is_regex": false,
    "enabled": true
}
```

### POST /web/autoreply/:id/edit

Edit an existing auto-reply rule.

### POST /web/autoreply/:id/delete

Delete an auto-reply rule.

---

## Error Responses

| HTTP Code | Meaning                                            |
| --------- | -------------------------------------------------- |
| 400       | Bad request — missing or invalid parameters        |
| 401       | Unauthorized — invalid or missing token            |
| 404       | Not found — session or group doesn't exist         |
| 409       | Conflict — session already exists                  |
| 429       | Rate limited — hourly send limit reached           |
| 500       | Internal error — something went wrong              |
| 503       | Session not connected — WA session is disconnected |

All error responses follow:

```json
{ "error": "Error description" }
// or
{ "success": false, "error": "Error description" }
```

When a session dies mid-request (503), the response includes:

```json
{
    "success": false,
    "error": "Sesi terputus, sedang reconnect otomatis.",
    "reconnecting": true
}
```

---

## Number Format

- All phone numbers use international format **without** `+` prefix
- Example: `6281234567890` (Indonesia)
- Group JIDs use format: `120363xxx@g.us`
- The API automatically converts numbers to WhatsApp JIDs internally

---

## Database Schema

| Table             | Purpose                                              | Retention   |
| ----------------- | ---------------------------------------------------- | ----------- |
| `wa_sessions`     | Session config (phone_id, label, api_token, webhook) | Permanent   |
| `contacts`        | Contact book (name, phone, notes)                    | Permanent   |
| `messages_log`    | Message history (in/out, status tracking)            | **30 days** |
| `broadcast_jobs`  | Broadcast history                                    | **30 days** |
| `autoreply_rules` | Auto-response rules (keyword → reply)                | Permanent   |
| `app_settings`    | Global key-value settings                            | Permanent   |

### Message Status Tracking

The `messages_log.status` field is automatically updated via WhatsApp `message_ack` events:

| Status      | Meaning                         |
| ----------- | ------------------------------- |
| `sent`      | Message sent to WhatsApp server |
| `delivered` | Delivered to recipient device   |
| `read`      | Read by recipient               |
| `played`    | Voice/video message played      |
| `failed`    | Delivery failed                 |
| `received`  | Incoming message                |
| `autoreply` | Sent by autoreply system        |
