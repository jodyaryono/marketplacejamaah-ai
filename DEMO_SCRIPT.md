# 🎬 Hackathon Demo Video Script
## Marketplace Jamaah AI × Arc Blockchain
### "Agentic Economy on Arc" — Lablab.ai April 2026

**Target length: 3–4 minutes | Tone: warm, confident, story-driven**

---

## OPENING — Hook (0:00–0:20)

**[Screen: Close-up of WhatsApp chat on a phone]**

> *"This is not a demo. This is already running in production. Right now, 253 members of Indonesian Muslim communities are buying and selling through WhatsApp — fully managed by AI, paid through the Arc blockchain."*

**[Cut to: live website at marketplacejamaah-ai.jodyaryono.id]**

---

## PROBLEM — Meet Bu Fatimah (0:20–0:55)

**[Screen: Illustration of an Indonesian woman in hijab holding a phone]**

> *"Meet Bu Fatimah. 52 years old. She sells hijabs from Tangerang, Indonesia."*

> *"Bu Fatimah is a natural at WhatsApp. But ask her to upload a product to Tokopedia — Indonesia's biggest marketplace — and she gives up at step 3. Too many forms, too many fields, too afraid to make a mistake."*

**[Screen: Split — Tokopedia signup form (many required fields) vs. a simple WhatsApp chat]**

> *"There are 220 million Muslims in Indonesia. Tens of millions have products to sell — but no online store. Not because they don't want one. Because the technology available simply wasn't built for them."*

> *"So what do they do? They use WhatsApp Groups as their store. They post product photos in the group. Write the price in the caption. Interested buyers DM them. Payment happens via manual bank transfer. No escrow. No buyer protection. No receipts. And a human admin who has to moderate the group 24 hours a day."*

---

## SOLUTION — The AI Admin WAG (0:55–1:40)

**[Screen: Demo dashboard — "The Real Problem" section]**

> *"Our insight: don't force Bu Fatimah to learn new technology. Bring the technology to where she already is — WhatsApp."*

**[Screen: Animation of the WAG flow in the demo dashboard]**

> *"Marketplace Jamaah AI is an AI Admin for WhatsApp Groups. Here's what it does:"*

> *"First — A new member requests to join. The AI automatically DMs them, collects a brief introduction, and approves or rejects based on their profile."*

**[Screen: Screenshot of bot greeting a new member]**

> *"Second — Bu Fatimah sends a photo and caption in the group: 'hijab sifon 50rb'. The AI reads it, extracts the title, price, and category — and the listing goes live on the website. Bu Fatimah never touched the website once."*

**[Screen: New listing appearing on the website]**

> *"Third — Spam? Hate speech? Scam attempts? The AI handles moderation. 24/7. No human admin needed."*

---

## INNOVATION — USYC Nanopayments on Arc (1:40–2:40)

**[Screen: hackathon-demo.html — chat simulator]**

> *"But here's the core innovation we built for this hackathon: payments."*

> *"Until now, payments in these WhatsApp communities were completely manual: bank transfer, share a screenshot, hope you don't get scammed. No escrow. No protection."*

> *"We integrated USYC — Circle's yield-bearing USD stablecoin — on top of the Arc blockchain L2."*

**[Screen: Type "mau beli hijab sifon" in chat simulator, watch pipeline animate]**

> *"A buyer types 'mau beli hijab' — 'I want to buy a hijab' — in WhatsApp. The AI Agent immediately goes to work:"*
> *"Step 1 — Intent detection using Google Gemini LLM with a keyword fallback for Indonesian language."*
> *"Step 2 — Listing match — finds the right product in the marketplace database."*
> *"Step 3 — Payment execution — transfers USYC to an escrow smart contract on Arc, in approximately 200 milliseconds."*
> *"Step 4 — Both buyer and seller receive automatic WhatsApp notifications with the transaction hash."*

**[Screen: TX hash appears + pipeline completes]**

> *"The buyer types 'confirm' once they receive the goods. Escrow releases. Seller gets paid. Fully autonomous."*

---

## YIELD — Money Working During Escrow (2:40–3:00)

**[Screen: Yield calculator slider in demo dashboard]**

> *"Here's what makes this unique: while funds sit in escrow, USYC is generating yield — approximately 5% APY from Circle."*

> *"Even a hijab worth $5 — if the escrow window is 24 hours — earns micro-yield. The money works while it waits."*

> *"And Arc L2 makes all of this possible: near-zero fees at 0.001 gwei. True nanopayments, finally accessible."*

---

## LIVE EVIDENCE (3:00–3:25)

**[Screen: Stats banner in demo dashboard]**

> *"This is not a proof of concept."*

> *"The platform has been running since March 8, 2026 — 6 weeks in production. Real numbers:"*
> *"253 community members. 98 active listings. 1,834 messages processed. 13 product categories. AI running 24/7."*

**[Screen: Live website marketplacejamaah-ai.jodyaryono.id]**

> *"What we added for this hackathon is the missing payment layer: USYC escrow on Arc — trustless, transparent, and yield-bearing."*

---

## CLOSING — Vision (3:25–3:50)

**[Screen: Marketplace Jamaah AI logo + Arc + USYC logos]**

> *"WhatsApp has 2 billion users. This model works for any community-based marketplace in the world."*

> *"Imagine decentralized commerce — mosque by mosque, community by community — all connected through WhatsApp, all payments trustless through Arc."*

> *"Bu Fatimah doesn't need to know what a blockchain is. She just needs to know how to send a photo on WhatsApp."*

**[Screen: WhatsApp chat — Bu Fatimah sends a hijab photo, listing appears on web, USYC payment confirmed]**

> *"Marketplace Jamaah AI. The Agentic Economy — starting from the jamaah."*

> *"Barakallahu fiikum."* 🕌

---

## RECORDING GUIDE

### Screens to record:
1. `https://marketplacejamaah-ai.jodyaryono.id` — live website with real products
2. `https://marketplacejamaah-ai.jodyaryono.id/hackathon-demo.html` — interactive demo
   - Click a listing (select any product)
   - Type `"mau beli hijab sifon"` → watch pipeline animate step by step
   - Type `"confirm"` → watch escrow release + yield display
   - Drag the yield calculator slider
3. Optional: brief WhatsApp Group screen recording (blur member names)

### Recording tips:
- Use OBS Studio or Loom (both free)
- Minimum 1080p resolution
- Narrate live or add voice-over after recording
- Optional: light instrumental background music (non-distracting)
- Keep it tight: **3–4 minutes max** (judges watch many videos)

### Upload to:
- YouTube (unlisted or public) OR Loom
- Paste the link into the Lablab.ai submission form

---

*Note: Keeping a few Indonesian phrases ("mau beli", "Bu Fatimah", "jamaah") is intentional —
they demonstrate authenticity and the real-world use case to the judges.*
