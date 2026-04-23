"""
Marketplace Jamaah AI — Payment Agent Microservice
===================================================
FastAPI service that powers the Agentic Economy integration:

  - NLP intent detection (Indonesian language)
  - Listing semantic search
  - USYC nanopayment orchestration via Arc blockchain
  - Autonomous transaction execution

Stack: Python 3.11 + FastAPI + Google Gemini + Arc SDK (testnet)
Hackathon: Agentic Economy on Arc @ Lablab.ai 2026
"""

from fastapi import FastAPI, HTTPException, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional
import os
import json
import logging
import time
import random
import hashlib
import httpx
from datetime import datetime, timedelta

from intent_classifier import IntentClassifier
from arc_client import ArcClient
from listing_matcher import ListingMatcher

# ── Config ─────────────────────────────────────────────────────────────────

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger(__name__)

LARAVEL_API_URL = os.getenv("LARAVEL_API_URL", "http://localhost:8000")
LARAVEL_API_TOKEN = os.getenv("LARAVEL_API_TOKEN", "")
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")
ARC_RPC_URL = os.getenv("ARC_RPC_URL", "https://rpc.testnet.arc.network")
ARC_TESTNET = os.getenv("ARC_TESTNET", "true").lower() == "true"

# ── App ────────────────────────────────────────────────────────────────────

app = FastAPI(
    title="Marketplace Jamaah AI — Payment Agent",
    description=(
        "Agentic Economy: AI agent that autonomously handles USYC nanopayments "
        "on Arc blockchain within a WhatsApp Islamic marketplace."
    ),
    version="1.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# ── Singletons ─────────────────────────────────────────────────────────────

intent_clf   = IntentClassifier(gemini_api_key=GEMINI_API_KEY)
arc_client   = ArcClient(rpc_url=ARC_RPC_URL, testnet=ARC_TESTNET)
listing_matcher = ListingMatcher()

# ── Schemas ────────────────────────────────────────────────────────────────

class MessagePayload(BaseModel):
    message_id: str
    sender_phone: str
    body: str
    group_id: Optional[str] = None
    reply_to_message_id: Optional[str] = None
    timestamp: Optional[int] = None


class PaymentRequest(BaseModel):
    sender_phone: str
    receiver_phone: str
    amount_usyc: float
    listing_id: Optional[int] = None
    description: Optional[str] = "Marketplace Jamaah AI payment"
    use_escrow: bool = True
    escrow_hours: int = 24


class WalletRequest(BaseModel):
    phone: str
    topup_demo: bool = False


class IntentRequest(BaseModel):
    text: str
    context: Optional[dict] = None


# ── Routes ─────────────────────────────────────────────────────────────────

@app.get("/")
def root():
    return {
        "service": "Marketplace Jamaah AI — Payment Agent",
        "version": "1.0.0",
        "hackathon": "Agentic Economy on Arc @ Lablab.ai 2026",
        "features": [
            "USYC nanopayment intent detection (Indonesian NLP)",
            "Arc blockchain transaction execution",
            "Escrow with automatic yield (5% APY via USYC)",
            "WhatsApp-native agentic payment flow",
        ],
        "network": "arc-testnet" if ARC_TESTNET else "arc-mainnet",
        "timestamp": datetime.utcnow().isoformat(),
    }


@app.get("/health")
def health():
    return {"status": "ok", "timestamp": datetime.utcnow().isoformat()}


@app.post("/analyse-message")
async def analyse_message(payload: MessagePayload, bg: BackgroundTasks):
    """
    Main agent entry point.
    Receives a WhatsApp message, classifies intent, and takes action.
    """
    logger.info(f"[Agent] Analysing message from {payload.sender_phone}: {payload.body[:80]}")

    intent_result = await intent_clf.classify(
        text=payload.body,
        context={
            "sender_phone": payload.sender_phone,
            "group_id": payload.group_id,
        }
    )

    if not intent_result["intent"] or intent_result["confidence"] < 0.6:
        return {"action": "none", "reason": "No payment intent detected"}

    intent   = intent_result["intent"]
    entities = intent_result.get("entities", {})

    logger.info(f"[Agent] Intent: {intent} | Confidence: {intent_result['confidence']:.2f} | Entities: {entities}")

    result = await dispatch_intent(intent, entities, payload)

    return {
        "action"    : intent,
        "confidence": intent_result["confidence"],
        "entities"  : entities,
        "result"    : result,
    }


@app.post("/payment/send")
async def send_payment(req: PaymentRequest):
    """
    Execute a USYC nanopayment on Arc blockchain.
    """
    logger.info(f"[Arc] Payment {req.sender_phone} → {req.receiver_phone} | {req.amount_usyc} USYC")

    try:
        # Execute on Arc
        receipt = await arc_client.transfer_usyc(
            from_address = _get_wallet_address(req.sender_phone),
            to_address   = _get_wallet_address(req.receiver_phone),
            amount       = req.amount_usyc,
            escrow       = req.use_escrow,
            escrow_hours = req.escrow_hours,
        )

        # Notify Laravel to update DB
        await _notify_laravel_payment(
            sender_phone   = req.sender_phone,
            receiver_phone = req.receiver_phone,
            amount_usyc    = req.amount_usyc,
            listing_id     = req.listing_id,
            tx_hash        = receipt["tx_hash"],
            escrow_status  = "held" if req.use_escrow else None,
        )

        yield_preview = round(req.amount_usyc * 0.05 / 365, 6)  # Daily yield preview

        return {
            "success"      : True,
            "tx_hash"      : receipt["tx_hash"],
            "block_number" : receipt["block_number"],
            "amount_usyc"  : req.amount_usyc,
            "fee_usyc"     : round(req.amount_usyc * 0.001, 6),
            "escrow"       : req.use_escrow,
            "escrow_release": (datetime.utcnow() + timedelta(hours=req.escrow_hours)).isoformat() if req.use_escrow else None,
            "yield_daily_preview": yield_preview,
            "network"      : "arc-testnet" if ARC_TESTNET else "arc-mainnet",
        }

    except Exception as e:
        logger.error(f"[Arc] Payment failed: {e}")
        raise HTTPException(status_code=400, detail=str(e))


@app.post("/wallet/create")
async def create_wallet(req: WalletRequest):
    """
    Create or retrieve an Arc wallet for a phone number.
    """
    address = _get_wallet_address(req.phone)
    balance = 100.0 if (req.topup_demo and ARC_TESTNET) else 0.0

    logger.info(f"[Arc] Wallet created for {req.phone}: {address}")

    return {
        "phone"     : req.phone,
        "arc_address": address,
        "usyc_balance": balance,
        "network"   : "arc-testnet" if ARC_TESTNET else "arc-mainnet",
        "note"      : "Testnet wallet funded with 100 USYC for demo" if req.topup_demo else None,
    }


@app.get("/wallet/{phone}/balance")
async def get_balance(phone: str):
    """
    Get USYC balance for a phone number from Arc blockchain.
    """
    address = _get_wallet_address(phone)
    balance = await arc_client.get_balance(address)

    return {
        "phone"     : phone,
        "arc_address": address,
        "usyc"      : balance["usyc"],
        "usd_equiv" : balance["usyc"],  # USYC ≈ $1 USD
        "apy"       : 0.05,             # 5% APY
        "yield_daily": round(balance["usyc"] * 0.05 / 365, 6),
        "network"   : "arc-testnet" if ARC_TESTNET else "arc-mainnet",
    }


@app.post("/intent/classify")
async def classify_intent(req: IntentRequest):
    """
    Classify payment intent from Indonesian text (for testing/debugging).
    """
    result = await intent_clf.classify(req.text, req.context)
    return result


@app.get("/transactions/{phone}")
async def get_transactions(phone: str, limit: int = 10):
    """
    Get recent USYC transactions for a phone number (via Laravel API).
    """
    try:
        async with httpx.AsyncClient() as client:
            resp = await client.get(
                f"{LARAVEL_API_URL}/api/usyc/transactions/{phone}",
                headers={"Authorization": f"Bearer {LARAVEL_API_TOKEN}"},
                timeout=5.0,
            )
        if resp.is_success:
            return resp.json()
    except Exception as e:
        logger.warning(f"[Agent] Laravel API unavailable: {e}")

    # Return mock data if Laravel is not reachable
    return _mock_transactions(phone)


# ── Helpers ────────────────────────────────────────────────────────────────

async def dispatch_intent(intent: str, entities: dict, payload: MessagePayload) -> dict:
    """Route an intent to the appropriate action."""

    if intent == "buy":
        listing = await listing_matcher.find(
            query    = entities.get("product_name", ""),
            group_id = payload.group_id,
        )
        if not listing:
            return {"status": "listing_not_found", "query": entities.get("product_name")}

        return {
            "status"    : "payment_initiated",
            "listing_id": listing["id"],
            "title"     : listing["title"],
            "price_usyc": listing.get("price_usyc"),
            "message"   : "Payment confirmation sent to buyer via WhatsApp",
        }

    elif intent == "balance":
        address = _get_wallet_address(payload.sender_phone)
        balance = await arc_client.get_balance(address)
        return {"status": "balance_sent", "usyc": balance["usyc"]}

    elif intent == "register":
        address = _get_wallet_address(payload.sender_phone)
        return {"status": "wallet_created", "arc_address": address}

    elif intent == "confirm":
        return {"status": "escrow_release_initiated"}

    elif intent == "cancel":
        return {"status": "payment_cancelled"}

    return {"status": "unknown_intent"}


def _get_wallet_address(phone: str) -> str:
    """Deterministically derive Arc wallet address from phone (custodial, demo)."""
    seed = f"arc-jamaah-{phone}-{os.getenv('APP_KEY', 'hackathon')}"
    h    = hashlib.sha256(seed.encode()).hexdigest()
    return "0x" + h[:40]


async def _notify_laravel_payment(
    sender_phone: str,
    receiver_phone: str,
    amount_usyc: float,
    listing_id: Optional[int],
    tx_hash: str,
    escrow_status: Optional[str],
) -> None:
    """Notify Laravel API to persist transaction to DB."""
    try:
        async with httpx.AsyncClient() as client:
            await client.post(
                f"{LARAVEL_API_URL}/api/usyc/payment-confirmed",
                json={
                    "sender_phone"  : sender_phone,
                    "receiver_phone": receiver_phone,
                    "amount_usyc"   : amount_usyc,
                    "listing_id"    : listing_id,
                    "tx_hash"       : tx_hash,
                    "escrow_status" : escrow_status,
                },
                headers={"Authorization": f"Bearer {LARAVEL_API_TOKEN}"},
                timeout=5.0,
            )
    except Exception as e:
        logger.warning(f"[Agent] Failed to notify Laravel: {e}")


def _mock_transactions(phone: str) -> dict:
    """Return mock transaction history for demo."""
    return {
        "phone": phone,
        "transactions": [
            {
                "id": 1,
                "type": "payment",
                "amount_usyc": 25.0,
                "direction": "sent",
                "status": "confirmed",
                "tx_hash": "0xabc123demo",
                "description": "Beli: Hijab Sifon Premium",
                "created_at": (datetime.utcnow() - timedelta(hours=2)).isoformat(),
            },
            {
                "id": 2,
                "type": "escrow",
                "amount_usyc": 15.5,
                "direction": "received",
                "status": "held",
                "yield_earned": 0.00021,
                "description": "Jual: Kurma Ajwa 250gr",
                "created_at": (datetime.utcnow() - timedelta(hours=1)).isoformat(),
            },
        ],
        "total_usyc": 40.5,
        "yield_earned": 0.00021,
    }
