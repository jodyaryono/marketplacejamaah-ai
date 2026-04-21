"""
ListingMatcher
==============
Finds marketplace listings that match a buyer's intent query.
Uses lightweight TF-IDF similarity for hackathon; production would
use a vector embedding store (pgvector, Pinecone, etc.).
"""

import re
import math
import logging
import httpx
import os
from typing import Optional

logger = logging.getLogger(__name__)

LARAVEL_API_URL   = os.getenv("LARAVEL_API_URL", "http://localhost:8000")
LARAVEL_API_TOKEN = os.getenv("LARAVEL_API_TOKEN", "")

# Hardcoded demo listings for offline/hackathon mode
DEMO_LISTINGS = [
    {"id": 1,  "title": "Hijab Sifon Premium", "description": "Hijab sifon warna-warni, adem dan nyaman",
     "price_usyc": 5.0, "accepts_usyc": True, "status": "active"},
    {"id": 2,  "title": "Kurma Ajwa 250gr", "description": "Kurma Ajwa asli Madinah",
     "price_usyc": 12.5, "accepts_usyc": True, "status": "active"},
    {"id": 3,  "title": "Buku Fiqih Muamalah", "description": "Buku panduan transaksi islami",
     "price_usyc": 8.0, "accepts_usyc": True, "status": "active"},
    {"id": 4,  "title": "Minyak Zaitun Extra Virgin 500ml", "description": "Minyak zaitun murni cold pressed",
     "price_usyc": 15.0, "accepts_usyc": True, "status": "active"},
    {"id": 5,  "title": "Parfum Oud Arabia", "description": "Parfum non-alkohol, wangi seharian",
     "price_usyc": 20.0, "accepts_usyc": True, "status": "active"},
]


class ListingMatcher:
    def __init__(self):
        self._listings_cache: list = []
        self._cache_ttl: int = 300  # 5 minutes

    async def find(self, query: str, group_id: Optional[str] = None, top_k: int = 1) -> Optional[dict]:
        """
        Find the best matching listing for a query string.
        Returns the top match or None if no good match found.
        """
        if not query or len(query.strip()) < 2:
            return None

        listings = await self._get_listings(group_id)

        if not listings:
            return None

        scored = [
            (self._similarity(query.lower(), listing), listing)
            for listing in listings
            if listing.get("accepts_usyc") and listing.get("status") == "active"
        ]

        scored.sort(key=lambda x: x[0], reverse=True)

        if not scored or scored[0][0] < 0.1:
            return None

        best_score, best_listing = scored[0]
        logger.info(f"[ListingMatcher] Best match: '{best_listing['title']}' (score={best_score:.2f})")
        return best_listing

    async def _get_listings(self, group_id: Optional[str]) -> list:
        """Fetch listings from Laravel API, fall back to demo data."""
        try:
            params = {"status": "active", "accepts_usyc": 1}
            if group_id:
                params["group_id"] = group_id

            async with httpx.AsyncClient(timeout=3.0) as client:
                resp = await client.get(
                    f"{LARAVEL_API_URL}/api/usyc/listings",
                    params=params,
                    headers={"Authorization": f"Bearer {LARAVEL_API_TOKEN}"},
                )
                if resp.is_success:
                    data = resp.json()
                    return data.get("listings", [])
        except Exception as e:
            logger.debug(f"[ListingMatcher] API unavailable, using demo: {e}")

        return DEMO_LISTINGS

    def _similarity(self, query: str, listing: dict) -> float:
        """Simple token overlap similarity."""
        query_tokens = set(re.split(r'\s+', query.lower()))
        title_tokens = set(re.split(r'\s+', listing.get("title", "").lower()))
        desc_tokens  = set(re.split(r'\s+', listing.get("description", "").lower()))
        all_tokens   = title_tokens | desc_tokens

        if not query_tokens or not all_tokens:
            return 0.0

        intersection = query_tokens & all_tokens
        # Weight title matches more heavily
        title_overlap = len(query_tokens & title_tokens)
        desc_overlap  = len(query_tokens & desc_tokens)

        score = (title_overlap * 2 + desc_overlap) / (len(query_tokens) + 1)
        return min(score, 1.0)
