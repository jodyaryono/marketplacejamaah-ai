"""
IntentClassifier
================
Classifies Indonesian WhatsApp messages into payment intents using
Google Gemini LLM with structured output. Falls back to keyword
matching if the API is unavailable.
"""

import os
import re
import json
import logging
import httpx
from typing import Optional

logger = logging.getLogger(__name__)

INTENTS = {
    "buy": {
        "keywords": ["mau beli", "ingin beli", "order", "pesan", "beli ", "ambil", "mau ambil", "tertarik", "minat"],
        "description": "User wants to purchase a listed item",
    },
    "balance": {
        "keywords": ["cek saldo", "saldo saya", "saldo usyc", "berapa saldo", "lihat saldo", "wallet"],
        "description": "User wants to check their USYC wallet balance",
    },
    "register": {
        "keywords": ["daftar wallet", "buat wallet", "register wallet", "aktivasi", "daftar usyc"],
        "description": "User wants to create/register an Arc wallet",
    },
    "confirm": {
        "keywords": ["confirm", "sudah terima", "barang sampai", "release", "oke bayar", "konfirmasi terima"],
        "description": "Buyer confirms item received, release escrow",
    },
    "cancel": {
        "keywords": ["cancel", "batal", "batalkan", "refund"],
        "description": "User wants to cancel a payment",
    },
    "topup": {
        "keywords": ["topup", "top up", "isi saldo", "tambah saldo", "deposit usyc"],
        "description": "User wants to top up USYC balance",
    },
}

GEMINI_PROMPT = """
You are a payment intent classifier for a WhatsApp Islamic marketplace in Indonesia.
Classify the user message into one of these intents: buy, balance, register, confirm, cancel, topup, or none.

Also extract relevant entities:
- product_name: the item they want to buy (if intent=buy)
- amount: any USYC amount mentioned
- phone: any phone number mentioned

Return ONLY valid JSON:
{{
  "intent": "<intent or null>",
  "confidence": <0.0-1.0>,
  "entities": {{
    "product_name": "<extracted product or null>",
    "amount": <number or null>
  }},
  "reasoning": "<brief explanation>"
}}

User message (Indonesian):
"{text}"
"""


class IntentClassifier:
    def __init__(self, gemini_api_key: str = ""):
        self.gemini_api_key = gemini_api_key
        self.use_llm = bool(gemini_api_key)

    async def classify(self, text: str, context: Optional[dict] = None) -> dict:
        """
        Classify intent from text.
        Uses Gemini LLM if configured, falls back to keyword matching.
        """
        text_lower = text.lower().strip()

        if self.use_llm:
            try:
                return await self._classify_with_gemini(text)
            except Exception as e:
                logger.warning(f"[IntentClassifier] Gemini failed, using keyword fallback: {e}")

        return self._classify_keywords(text_lower)

    async def _classify_with_gemini(self, text: str) -> dict:
        """Use Gemini API for intent classification with entity extraction."""
        url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={self.gemini_api_key}"

        prompt = GEMINI_PROMPT.format(text=text)

        async with httpx.AsyncClient(timeout=10.0) as client:
            resp = await client.post(url, json={
                "contents": [{"parts": [{"text": prompt}]}],
                "generationConfig": {
                    "temperature": 0.1,
                    "maxOutputTokens": 300,
                    "responseMimeType": "application/json",
                },
            })
            resp.raise_for_status()

        raw = resp.json()
        content = raw["candidates"][0]["content"]["parts"][0]["text"]

        # Parse JSON response
        result = json.loads(content)

        if result.get("intent") not in [*INTENTS.keys(), None]:
            result["intent"] = None

        return result

    def _classify_keywords(self, text: str) -> dict:
        """Keyword-based fallback classifier."""
        for intent, config in INTENTS.items():
            for kw in config["keywords"]:
                if kw in text:
                    entities = {}
                    if intent == "buy":
                        entities["product_name"] = self._extract_product(text)
                    return {
                        "intent"    : intent,
                        "confidence": 0.85,
                        "entities"  : entities,
                        "reasoning" : f"Keyword match: '{kw}'",
                    }

        return {
            "intent"    : None,
            "confidence": 0.0,
            "entities"  : {},
            "reasoning" : "No payment intent detected",
        }

    def _extract_product(self, text: str) -> Optional[str]:
        """Extract product name from buy intent text."""
        patterns = [
            r"(?:mau beli|ingin beli|beli|order|pesan|ambil)\s+(.+?)(?:\s+dong|\s+ya|\s+nih|$)",
        ]
        for pattern in patterns:
            m = re.search(pattern, text, re.IGNORECASE)
            if m:
                return m.group(1).strip()
        return None
