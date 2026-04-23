# 🕌 Marketplace Jamaah AI × Arc Blockchain
### Hackathon: **Agentic Economy on Arc** @ [Lablab.ai](https://lablab.ai) — April 2026
### Prize: $20,000+ | Deadline: April 26, 2026

---

## 🚀 This Is NOT A Demo — It's Already Live

> **Marketplace Jamaah AI is a production platform actively serving real Indonesian Muslim (jamaah) communities via WhatsApp.**

| Metric | Real Number |
|--------|------------|
| 👥 Total Community Members | **253 contacts** |
| ✅ Registered Active Members | **23 members** |
| 🛍️ Total Listings Ever Posted | **181 listings** |
| 📦 Currently Active Listings | **98 active products** |
| 📱 WhatsApp Groups | **1 active jamaah group** |
| 💬 Total Messages Processed | **1,834 messages** |
| 💬 Messages (last 30 days) | **539 messages** |
| 🏷️ Product Categories | **13 categories** |
| 📅 Platform Running Since | **March 8, 2026** (~6 weeks live) |
| 🌐 Live URL | [marketplacejamaah-ai.jodyaryono.id](https://marketplacejamaah-ai.jodyaryono.id) |
| 🤖 AI Agents | Running **24/7** in production |

### Real Product Categories (Live Data)
| Category | Active Listings |
|----------|----------------|
| 🍱 Food & Beverages (Makanan & Minuman) | 26 |
| 🔧 Services (Jasa & Layanan) | 22 |
| 🏠 Property (Properti) | 13 |
| 🌾 Groceries (Sembako) | 11 |
| 🚗 Vehicles (Kendaraan) | 8 |
| 📦 Other (Lainnya) | 6 |
| 🛋️ Home Goods (Peralatan Rumah) | 2 |
| 💊 Health & Beauty (Kesehatan & Kecantikan) | 2 |
| 📚 Books & Education (Buku & Pendidikan) | 2 |
| ⚽ Hobbies & Sports (Hobi & Olahraga) | 2 |
| 📱 Electronics (Elektronik) | 1 |

**The USYC + Arc integration built for this hackathon adds the missing trustless payment layer to this already-functioning live marketplace — serving 253 real community members in Indonesia.**

---

## 🎯 The Real Problem — Meet Bu Fatimah

> *"Bu Fatimah, 52 years old, hijab seller from Tangerang, Indonesia. She's fluent on WhatsApp, but has never once managed to upload a product to Tokopedia or Shopee. Too complicated, too many steps, afraid of making mistakes."*

Indonesia has **220+ million Muslims** and tens of millions of small sellers like Bu Fatimah — housewives, market traders, small UMKM businesses — who:

- ✅ **Know WhatsApp** — they use it every single day
- ❌ **Can't upload to marketplaces** — too many forms, need proper photos, product descriptions, SKUs, shipping configurations, etc.
- ❌ **Don't have an online store** — subscription costs, complicated setup
- ❌ **Are not tech-savvy ("gaptek")** — comfortable with a smartphone, but not a laptop or complex web forms

### What They Do Instead

Bu Fatimah and millions of sellers like her use **WhatsApp Groups (WAG)** as their store:

```
Bu Fatimah posts a hijab photo in the jamaah WhatsApp Group
→ Writes price & description in the caption
→ Interested buyers DM her directly
→ Payment via manual bank transfer
→ Ships the item
```

Simple. Familiar. No technical skill required. **But full of problems:**

- 🔴 Listings get buried under hundreds of group messages
- 🔴 No records — products are impossible to find later
- 🔴 Spam & irrelevant ads ruin the group experience
- 🔴 Human admins burn out moderating the group 24 hours a day
- 🔴 Manual bank transfer — prone to fraud, no escrow, no buyer protection
- 🔴 No verifiable transaction records

### The Insight

> **Don't force Bu Fatimah to learn new technology. Bring the technology to where she already is — WhatsApp.**

---

## 💡 Our Solution — AI Admin WAG + USYC Nanopayments

### How It Works (Zero Learning Curve)

**Bu Fatimah does NOT need to:**
- Open a website
- Create an account
- Upload photos to a form
- Write a long product description
- Learn anything new

**Bu Fatimah only needs to:**
1. **Join the WhatsApp Group** → AI Admin greets her automatically, asks for a brief intro
2. **Post a photo + caption in the group** → AI reads it, extracts title/price/category automatically
3. **Listing goes live on the website** → without Bu Fatimah ever touching the website
4. **Buyer types "mau beli"** → AI Agent processes USYC payment on Arc automatically

### AI Admin WAG — Working Behind the Scenes

```
New member requests to join the group
    ↓
AI Admin sends automatic DM: "Hi! Can we get acquainted? Name, city, buying or selling?"
    ↓
AI reads the reply → saves profile → approves/rejects join request
    ↓
Member posts photo + "hijab sifon 50rb" (hijab chiffon IDR 50,000)
    ↓
AI extracts: Title, Price, Category, Photo → listing goes live on website
    ↓
AI moderates: negative content, spam, hate speech → auto-warned/blocked
    ↓
Buyer DMs: "mau beli hijab" → AI checks USYC balance → processes escrow on Arc
    ↓
Both buyer and seller receive WhatsApp notifications → transaction complete
```

### Comparison

| | Tokopedia/Shopee | Manual WAG | **Marketplace Jamaah AI** |
|---|---|---|---|
| Account required | ✅ Mandatory | ❌ None | ❌ Not required |
| Product listing | Long form | Photo in group | **Photo in group → auto** |
| Tech skill needed | Intermediate | Low | **Anyone can do it** |
| Moderation | Automated | Human admin | **AI 24/7** |
| Payment | Fee 2–3% | Manual transfer | **USYC on Arc, 0.1% fee** |
| Escrow | Yes | No | **Smart contract on Arc** |
| Yield during escrow | No | No | **5% APY via USYC** |
| Target user | Tech-literate | Everyone | **Even non-tech users** |

---

## 💡 Core Innovation: Agentic Economy with USYC Nanopayments

We integrated **USYC (USD Yield Coin) nanopayments on Arc blockchain** into the existing
Marketplace Jamaah AI platform. AI agents can now autonomously handle the full payment lifecycle
directly within WhatsApp — no new apps, no bank transfers, no trust issues.

### What Makes This Different

1. **WhatsApp → AI Agent → Arc Blockchain** in one seamless flow
2. **Nanopayments** for small Indonesian marketplace transactions ($2–$50)
3. **USYC escrow** that earns yield while the buyer waits to confirm receipt
4. **Agentic economy**: the AI agent detects intent, matches the listing, and executes on-chain — fully autonomously

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
- Buyer types `"mau beli [product]"` in WhatsApp
- Agent classifies intent (Indonesian NLP with Gemini LLM fallback)
- Matches listing in marketplace database
- Executes USYC transfer on Arc blockchain
- Funds held in escrow smart contract
- Buyer types `"confirm"` → funds released to seller

### 2. Yield-Bearing Escrow
- While funds are in escrow, USYC earns ~5% APY (Circle's yield mechanism)
- Small bonus yield goes to the party that waits
- Even a $5 hijab purchase earns micro-yield during a 24h escrow window

### 3. Arc L2 Advantages
- **~200ms finality** — faster than a bank transfer confirmation SMS
- **Near-zero gas** (0.001 gwei) — enables true nanopayments
- **USYC native** — USYC is the natural payment token on Arc

### 4. Indonesian Language AI
- Full Indonesian intent detection: "mau beli", "cek saldo", "daftar wallet"
- Gemini LLM with keyword fallback for reliability
- Respectful Islamic greetings and messaging style

### 5. Zero UX Friction
- **No new app required** — buyers/sellers use WhatsApp as-is
- **No seed phrases exposed** — custodial Arc wallets, phone-number keyed
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
| `mau beli [product]` | Buy intent → escrow payment |
| `confirm` | Confirm receipt → release escrow |
| `cancel` | Cancel pending payment |
| `topup` | Top up USYC via Circle |

> Note: Commands are intentionally in Indonesian — the natural language of the users. The AI handles English commands as well.

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
Open `public/hackathon-demo.html` in any browser — no build step needed.

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
| **USYC Innovation** | Yield-bearing escrow — money earns even while locked |
| **Real Use Case** | Live marketplace with 250+ real Indonesian users — not a toy demo |
| **Scale** | WhatsApp has 2B users; this model scales to any Muslim marketplace globally |
| **Halal Finance** | Escrow model eliminates riba (interest) risk in Islamic commerce |

---

## 👨‍💻 Team

**Jody Aryono** — Founder, Marketplace Jamaah AI
- Email: jodyaryono@gmail.com
- Platform: https://marketplacejamaah-ai.jodyaryono.id
- Stack: Laravel + Python AI + WhatsApp Gateway + Arc Blockchain

---

## 📜 License

MIT — Open source, hackathon submission.

*Barakallahu fiikum — may this technology bring blessing to the jamaah.* 🕌
