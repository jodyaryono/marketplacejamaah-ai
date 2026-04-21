"""
ArcClient
=========
Python client for Arc blockchain USYC nanopayments.

In testnet mode, all transactions are simulated with realistic
mock receipts. In production, connects to the real Arc RPC.

Arc blockchain features used:
  - USYC ERC-20 token transfers (nanopayments)
  - Escrow smart contract
  - Yield accrual tracking (5% APY)
  - Sub-second finality (Arc L2)
"""

import os
import random
import hashlib
import logging
import asyncio
from typing import Optional
import httpx
from datetime import datetime

logger = logging.getLogger(__name__)

# USYC token contract address on Arc (testnet)
USYC_CONTRACT_TESTNET  = "0x1234567890AbcDEF1234567890abcdef12345678"
USYC_CONTRACT_MAINNET  = "0xUsycMainnetContractAddress0000000000000"

# Platform escrow contract
ESCROW_CONTRACT = "0xEscrowContractAddress00000000000000000000"


class ArcClient:
    def __init__(self, rpc_url: str = "https://rpc.arc-testnet.io", testnet: bool = True):
        self.rpc_url  = rpc_url
        self.testnet  = testnet
        self.contract = USYC_CONTRACT_TESTNET if testnet else USYC_CONTRACT_MAINNET
        self.network  = "arc-testnet" if testnet else "arc-mainnet"

    async def transfer_usyc(
        self,
        from_address: str,
        to_address: str,
        amount: float,
        escrow: bool = False,
        escrow_hours: int = 24,
    ) -> dict:
        """
        Transfer USYC from one Arc wallet to another.

        If escrow=True, funds are locked in the escrow smart contract
        and only released when buyer confirms or timer expires.
        While locked, USYC continues to earn yield.
        """
        logger.info(f"[Arc] Transfer {amount} USYC | {from_address[:10]}... → {to_address[:10]}...")

        if self.testnet:
            return await self._mock_transfer(from_address, to_address, amount, escrow)

        # Production Arc RPC call
        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                payload = {
                    "method": "usyc_transfer",
                    "params": {
                        "from": from_address,
                        "to": to_address if not escrow else ESCROW_CONTRACT,
                        "amount": self._to_wei(amount),
                        "token": self.contract,
                        "metadata": {
                            "escrow": escrow,
                            "escrow_beneficiary": to_address if escrow else None,
                            "escrow_duration_hours": escrow_hours if escrow else None,
                            "platform": "marketplace-jamaah-ai",
                        },
                    },
                    "id": 1,
                    "jsonrpc": "2.0",
                }

                resp = await client.post(f"{self.rpc_url}/rpc", json=payload)
                resp.raise_for_status()
                data = resp.json()

                if "error" in data:
                    raise Exception(f"Arc RPC error: {data['error']['message']}")

                return data["result"]

        except httpx.RequestError as e:
            raise Exception(f"Arc RPC unreachable: {e}")

    async def get_balance(self, address: str) -> dict:
        """Get USYC balance for an Arc wallet address."""
        if self.testnet:
            return await self._mock_balance(address)

        try:
            async with httpx.AsyncClient(timeout=10.0) as client:
                resp = await client.post(f"{self.rpc_url}/rpc", json={
                    "method": "usyc_balanceOf",
                    "params": {"address": address, "token": self.contract},
                    "id": 1,
                    "jsonrpc": "2.0",
                })
                resp.raise_for_status()
                data = resp.json()
                wei = data["result"]["balance"]
                return {
                    "usyc": self._from_wei(wei),
                    "apy": 0.05,
                    "address": address,
                    "network": self.network,
                }
        except Exception as e:
            logger.warning(f"[Arc] Balance query failed: {e}, returning mock")
            return await self._mock_balance(address)

    async def get_escrow(self, tx_hash: str) -> dict:
        """Get escrow status for a transaction."""
        if self.testnet:
            return {
                "tx_hash": tx_hash,
                "status": "held",
                "yield_earned": 0.00015,
                "release_at": (datetime.utcnow()).isoformat(),
                "network": self.network,
            }

        async with httpx.AsyncClient(timeout=10.0) as client:
            resp = await client.get(f"{self.rpc_url}/escrow/{tx_hash}")
            return resp.json()

    async def release_escrow(self, tx_hash: str, releaser_address: str) -> dict:
        """Release escrowed funds to beneficiary."""
        if self.testnet:
            return await self._mock_transfer(releaser_address, releaser_address, 0, False)

        async with httpx.AsyncClient(timeout=30.0) as client:
            resp = await client.post(f"{self.rpc_url}/rpc", json={
                "method": "usyc_releaseEscrow",
                "params": {
                    "tx_hash": tx_hash,
                    "releaser": releaser_address,
                },
                "id": 1,
                "jsonrpc": "2.0",
            })
            return resp.json().get("result", {})

    # ── Mock implementations ───────────────────────────────────────────────

    async def _mock_transfer(
        self,
        from_addr: str,
        to_addr: str,
        amount: float,
        escrow: bool,
    ) -> dict:
        """Simulate Arc L2 block time (fast: ~200ms)."""
        await asyncio.sleep(0.2)

        tx_hash     = "0x" + hashlib.sha256(f"{from_addr}{to_addr}{amount}{random.random()}".encode()).hexdigest()
        block_num   = random.randint(4_000_000, 5_000_000)
        gas_used    = random.randint(21_000, 45_000)

        receipt = {
            "tx_hash"      : tx_hash,
            "block_number" : str(block_num),
            "from"         : from_addr,
            "to"           : ESCROW_CONTRACT if escrow else to_addr,
            "amount_wei"   : self._to_wei(amount),
            "gas_used"     : gas_used,
            "gas_price_gwei": 0.001,  # Arc L2: near-zero gas
            "status"       : "success",
            "network"      : self.network,
            "block_time_ms": 200,
            "timestamp"    : datetime.utcnow().isoformat(),
            "escrow"       : escrow,
            "usyc_contract": self.contract,
        }

        logger.info(f"[Arc] Mock TX confirmed: {tx_hash[:20]}... | Block #{block_num}")
        return receipt

    async def _mock_balance(self, address: str) -> dict:
        """Return deterministic mock balance based on address."""
        # Deterministic but seemingly random balance for demo
        h = int(hashlib.sha256(address.encode()).hexdigest()[:8], 16)
        balance = round((h % 500) + (h % 100) / 100, 2)

        return {
            "usyc"   : balance,
            "apy"    : 0.05,
            "address": address,
            "network": self.network,
        }

    # ── Conversion helpers ─────────────────────────────────────────────────

    def _to_wei(self, amount: float) -> str:
        """USYC uses 6 decimal places."""
        return str(int(amount * 1_000_000))

    def _from_wei(self, wei: str) -> float:
        return int(wei) / 1_000_000
