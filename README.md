# Marketplace Jamaah AI

AI agent marketplace for the Muslim community — buy, sell, and offer products & services directly inside WhatsApp, with on-chain nanopayments settled in **USYC on Arc**.

> Built for the **Agentic Economy on Arc** Hackathon — [lablab.ai](https://lablab.ai)

## What it does

Jamaah (congregation members) send a WhatsApp message like *"jual kurma ajwa 500g 120rb"* or *"cari jasa bersih AC Jakarta Selatan"*. An AI agent classifies the intent, extracts listing fields, matches buyers to sellers across jamaah groups, and when a deal is agreed it collects a small **per-transaction nanofee in USYC** on the Arc network — too small to be viable on traditional rails, but native to agentic commerce.

- **WhatsApp-native UX** — no app to install; the marketplace lives where the community already chats.
- **AI ingestion & matching** — Python agent parses free-form Indonesian listings, normalizes phone numbers, and ranks matches.
- **USYC nanopayments on Arc** — listing-boost fees, successful-match fees, and seller payouts settle on-chain via the Arc payment service.
- **Public iklan baris** — listings also surface on a lightweight public landing page.

## Tech stack

| Layer | Stack |
|---|---|
| Backend / orchestration | **Laravel 12** (PHP 8.2), Laravel Reverb, Spatie Permission, queues |
| AI agent | **Python** (`ai_agent/` — intent classifier, listing matcher, Arc client) |
| Payments | **USYC on Arc** (`app/Services/ArcPaymentService.php`, `config/arc.php`) |
| Messaging gateway | Node.js WhatsApp gateway (`gateway/`) |
| Frontend | Blade + **Vite + Tailwind v4** |
| DB / cache | MySQL, Redis (via Laravel) |

## Repo layout

- `app/Agents/BroadcastAgent.php` — broadcast / fan-out logic
- `app/Jobs/ProcessMessageJob.php` — inbound WhatsApp message pipeline
- `app/Services/ArcPaymentService.php` — USYC/Arc payment integration
- `ai_agent/` — Python AI service (intent + matching + Arc client)
- `gateway/` — Node WhatsApp gateway
- `public/hackathon-demo.html` — hackathon demo page
- `HACKATHON.md` — hackathon submission notes
- `DEMO_SCRIPT.md` — demo walkthrough
- `deploy-hackathon.sh` — deploy script for the hackathon VPS

## Running locally

Prerequisites: PHP 8.2+, Composer, Node 18+, Python 3.10+, MySQL, Redis.

```bash
# 1. PHP / Laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# 2. Frontend
npm install
npm run dev        # or: npm run build

# 3. AI agent
cd ai_agent
pip install -r requirements.txt
./start.sh

# 4. Queues + app
php artisan queue:work
php artisan serve
```

Set Arc / USYC credentials in `.env` (see `config/arc.php` for the expected keys).

## Hackathon

See [HACKATHON.md](HACKATHON.md) for the submission writeup and [DEMO_SCRIPT.md](DEMO_SCRIPT.md) for the demo flow.

## License

MIT
