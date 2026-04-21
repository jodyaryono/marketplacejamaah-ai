# 🕌 Marketplace Jamaah AI × Arc Blockchain
### Hackathon: **Agentic Economy on Arc** @ [Lablab.ai](https://lablab.ai) — April 2026
### Prize: $20,000+ | Deadline: April 26, 2026

---

## 🚀 This Is NOT A Demo — It's Already Live

> **Marketplace Jamaah AI is a production platform actively serving real Indonesian Muslim (jamaah) communities via WhatsApp.**

| Metric | Real Number |
|--------|------------|
| 🛍️ Active Listings | **97 products** |
| 📦 Media-verified Listings | **81 products with photos/videos** |
| 🏷️ Product Categories | **13 categories** |
| 🌐 Live URL | [marketplacejamaah-ai.jodyaryono.id](https://marketplacejamaah-ai.jodyaryono.id) |
| 🤖 AI Agents | Running **24/7** in production |
| 📱 Channel | Real Indonesian jamaah WhatsApp groups |

### Real Product Categories on Platform
**Food & Beverages** (25) · **Services & Labor** (22) · **Property** (13) · **Basic Staples** (11) · **Vehicles** (8) · Electronics · Health & Beauty · Clothing & Fashion · Home Equipment · and more

### Sample Real Listings
- 🏠 Properties (310 juta - 2.35 miliar IDR)
- 🌴 Food products (kurma, sembako)
- 🚗 Vehicle sales
- 🕋 Hajj & Umroh services
- 📋 SIM & administrative services

**The USYC + Arc integration built for this hackathon adds the missing trustless payment layer to this already-functioning live marketplace.**

---

## 🎯 Problem Statement

Indonesia is home to **220+ million Muslims** — the largest Muslim population on Earth.
WhatsApp groups are the *de facto* marketplace for Indonesian Islamic communities (jamaah).
Products like hijabs, kurma, books, and halal goods are traded daily in thousands of groups,
but payments are still fully manual (bank transfer, OVO, cash on delivery).

**Pain points we observe from REAL users on our platform:**
- Manual payment confirmation is slow and error-prone
- No trustless escrow — buyer/seller trust is fragile, disputes happen
- Small vendors can't afford payment gateway fees (2-3%)
- Money sits idle while waiting for transactions to settle — no yield

---

## 💡 Our Solution: Agentic Economy with USYC Nanopayments

We integrated **USYC (USD Yield Coin) nanopayments on Arc blockchain** into the existing
Marketplace Jamaah AI platform. Now, AI agents can autonomously handle the full payment lifecycle
directly within WhatsApp — no apps, no bank transfers, no trust issues.

### Core Innovation

1. **WhatsApp → AI Agent → Arc Blockchain** in one seamless flow
2. **Nanopayments** for small Indonesian marketplace transactions ($2–$50)
3. **USYC escrow** that earns yield while buyer waits to confirm receipt
4. **Agentic economy**: AI agent detects intent, matches listing, and executes on-chain — autonomously

---

## 🏗️ Architecture

```
WhatsApp Message (buyer: "mau beli hijab")
    │
    ▼
Laravel Webhook (ProcessMessageJob)
    │
    ▼
PaymentAgent (PHP) ──── Intent Detection (Indonesian NLP)
    │                         │
    │                    Gemini LLM / keyword fallback
    │
    ▼
ListingMatcher ──── Finds matching USYC-enabled listing
    │
    ▼
ArcPaymentService (PHP) ──────────────────────────────────────────────┐
    │                                                                  │
    ▼                                                                  ▼
Python AI Microservice (FastAPI)                        Arc Blockchain (L2)
  ├── IntentClassifier (Gemini)                           ├── USYC ERC-20 Transfer
  ├── ListingMatcher (TF-IDF)                             ├── Escrow Smart Contract
  └── ArcClient (testnet/mainnet)                         └── Yield Accrual (5% APY)
    │
    ▼
WhatsApp Notification → Buyer & Seller
  + On-chain TX hash + Escrow confirmation
```

---

## 🔑 Key Features

### 1. USYC Nanopayment Flow
- Buyer types `"mau beli [produk]"` in WhatsApp
- Agent classifies intent (Indonesian NLP with Gemini LLM fallback)
- Matches listing in marketplace database
- Executes USYC transfer on Arc blockchain
- Funds held in escrow smart contract
- Buyer types `"confirm"` → funds released to seller

### 2. Yield-Bearing Escrow
- While funds are in escrow, USYC earns ~5% APY (Circle's yield mechanism)
- Small bonus yield goes to the party that waits
- Even a $5 hijab purchase earns micro-yield during 24h escrow

### 3. Arc L2 Advantages
- **~200ms finality** — faster than bank transfer confirmation SMS
- **Near-zero gas** (0.001 gwei) — enables true nanopayments
- **USYC native** — USYC is the natural payment token on Arc

### 4. Indonesian Language AI
- Full Indonesian intent detection: "mau beli", "cek saldo", "daftar wallet"
- Gemini LLM with keyword fallback for reliability
- Respectful Islamic greetings and messaging style

### 5. Zero UX Friction
- **No new app required** — buyers/sellers use WhatsApp as-is
- **No seed phrases exposed** — custodial Arc wallets, phone-keyed
- **No bank account needed** — USYC can be topped up via Circle

---

## 📂 New Files Added (Hackathon Integration)

```
app/
├── Models/
│   ├── UsycWallet.php              — Arc wallet model (off-chain ledger)
│   └── UsycTransaction.php         — USYC tx model with escrow support
├── Services/
│   └── ArcPaymentService.php       — Arc blockchain + USYC service layer
├── Agents/
│   └── PaymentAgent.php            — AI payment intent agent (WA → payment)
└── Http/Controllers/
    └── PaymentController.php       — REST API for payment operations

ai_agent/
├── main.py                         — FastAPI microservice (entry point)
├── intent_classifier.py            — Gemini LLM intent detection
├── arc_client.py                   — Arc blockchain Python client
├── listing_matcher.py              — Semantic listing search
└── requirements.txt

config/
└── arc.php                         — Arc blockchain config

database/migrations/
├── ..._create_usyc_wallets_table.php
└── ..._create_usyc_transactions_table.php

routes/
└── api.php                         — Added /api/usyc/* endpoints

public/
└── hackathon-demo.html             — Interactive demo dashboard
```

---

## 🚀 API Endpoints

### Wallets
```
GET  /api/usyc/wallet/{phone}         — Get/create Arc wallet
GET  /api/usyc/balance/{phone}        — Live balance from Arc
POST /api/usyc/wallet/topup-demo      — Testnet USYC topup
```

### Payments
```
POST /api/usyc/pay                    — Execute USYC payment + escrow
POST /api/usyc/payment-confirmed      — Python AI agent callback
POST /api/usyc/escrow/{tx}/release    — Release escrow to seller
```

### Listings
```
GET  /api/usyc/listings               — Get USYC-enabled listings
PATCH /api/usyc/listings/{id}/enable  — Enable USYC for a listing
```

### Analytics
```
GET  /api/usyc/transactions/{phone}   — Transaction history
GET  /api/usyc/stats                  — Platform-wide stats
```

### Python AI Agent
```
POST /analyse-message     — Intent detection + payment dispatch
POST /payment/send        — Arc USYC transfer
GET  /wallet/{phone}/balance
POST /intent/classify     — Debug intent classification
```

---

## 💬 WhatsApp Payment Commands

| Command | Action |
|---------|--------|
| `daftar wallet` | Create Arc wallet (100 USYC testnet) |
| `cek saldo` | View USYC balance |
| `mau beli [produk]` | Buy intent → escrow payment |
| `confirm` | Confirm receipt → release escrow |
| `cancel` | Cancel pending payment |
| `topup` | Top up USYC via Circle |

---

## 🛠️ Setup

### 1. Laravel (existing)
```bash
# Add to .env
ARC_RPC_URL=https://rpc.arc-testnet.io
ARC_OPERATOR_ADDRESS=0x...
ARC_OPERATOR_PK=...
ARC_USYC_CONTRACT=0x...
ARC_TESTNET=true
GEMINI_API_KEY=...

# Run migrations
php artisan migrate
```

### 2. Python AI Agent
```bash
cd ai_agent
pip install -r requirements.txt

# .env
LARAVEL_API_URL=http://localhost:8000
LARAVEL_API_TOKEN=your-token
GEMINI_API_KEY=your-key
ARC_RPC_URL=https://rpc.arc-testnet.io
ARC_TESTNET=true

# Start
uvicorn main:app --host 0.0.0.0 --port 8001 --reload
```

### 3. Demo Dashboard
Open `public/hackathon-demo.html` in browser — no build needed.

---

## 🎥 Demo Flow

1. Open `https://marketplacejamaah-ai.jodyaryono.id/hackathon-demo.html`
2. Click a listing to select it
3. Type `"mau beli hijab sifon"` and press Enter
4. Watch the agent pipeline animate (intent → match → Arc → escrow)
5. Type `"confirm"` to release escrow
6. See Arc TX hash + block number + yield calculation

---

## 🏆 Why This Wins

| Criterion | Our Solution |
|-----------|-------------|
| **Agentic Economy** | AI agents autonomously execute real payments, not just chat |
| **Arc Integration** | Native Arc L2 for USYC transfers + escrow smart contracts |
| **USYC Innovation** | Yield-bearing escrow — money works even while locked |
| **Real Use Case** | Live marketplace with 100+ Indonesian users, not a demo toy |
| **Scale** | WhatsApp has 2B users; this model scales to any Muslim marketplace globally |
| **Halal Finance** | Escrow removes riba (interest) risk in Islamic commerce |

---

## 👨‍💻 Team

**Jody Aryono** — Founder, Marketplace Jamaah AI
- Email: jodyaryono@gmail.com
- Platform: https://marketplacejamaah-ai.jodyaryono.id
- Tech: Laravel + Python AI + WhatsApp Gateway + Arc Blockchain

---

## 📜 License

MIT — Open source, hackathon submission.

*Barakallahu fiikum — may this technology bring blessing to the jamaah.* 🕌
