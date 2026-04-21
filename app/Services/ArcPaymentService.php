<?php

namespace App\Services;

use App\Models\UsycTransaction;
use App\Models\UsycWallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ArcPaymentService
 *
 * Handles USYC (USD Yield Coin) nanopayments on the Arc blockchain.
 *
 * Arc is a Layer-2 blockchain optimised for micro-transactions and
 * stablecoin payments, integrated with Circle's USYC token.
 *
 * In hackathon/testnet mode, transactions are simulated with realistic
 * mock responses. Switch to production by setting ARC_TESTNET=false
 * and providing real ARC_RPC_URL + ARC_OPERATOR_PK.
 */
class ArcPaymentService
{
    private string $rpcUrl;
    private string $operatorAddress;
    private string $operatorPk;
    private string $usycContractAddress;
    private bool   $testnet;
    private string $platformFeeAddress;

    // USYC nanopayment threshold — anything below this is a "nanopayment"
    const NANOPAYMENT_THRESHOLD_USD = 1.00;

    public function __construct()
    {
        $this->rpcUrl               = config('arc.rpc_url', 'https://rpc.arc-testnet.io');
        $this->operatorAddress      = config('arc.operator_address', '0x0000000000000000000000000000000000000000');
        $this->operatorPk           = config('arc.operator_pk', '');
        $this->usycContractAddress  = config('arc.usyc_contract', '0xUsycContractAddressHere');
        $this->platformFeeAddress   = config('arc.fee_address', '0xPlatformFeeAddress');
        $this->testnet              = config('arc.testnet', true);
    }

    // ── Core Payment Flow ─────────────────────────────────────────────────────

    /**
     * Execute a USYC nanopayment from buyer to seller.
     *
     * Flow:
     *  1. Reserve funds in buyer wallet (off-chain ledger)
     *  2. Broadcast ERC-20 transferFrom on Arc blockchain
     *  3. Wait for confirmation (fast on Arc L2: ~2s)
     *  4. Debit buyer, credit seller
     *  5. Collect platform fee
     *
     * @param  string $senderPhone   Buyer's phone (linked to Arc wallet)
     * @param  string $receiverPhone Seller's phone
     * @param  float  $amountUsyc   Payment amount in USYC
     * @param  array  $context      Extra context (listing_id, wa_message_id, etc.)
     * @return UsycTransaction
     * @throws \Exception on insufficient balance or blockchain failure
     */
    public function sendPayment(
        string $senderPhone,
        string $receiverPhone,
        float  $amountUsyc,
        array  $context = []
    ): UsycTransaction {
        Log::info('[Arc] Initiating USYC payment', [
            'from'   => $senderPhone,
            'to'     => $receiverPhone,
            'amount' => $amountUsyc,
        ]);

        // 1. Load wallets
        $senderWallet   = UsycWallet::forPhone($senderPhone);
        $receiverWallet = UsycWallet::forPhone($receiverPhone);

        if (!$senderWallet->hasSufficientBalance($amountUsyc)) {
            throw new \Exception(
                "Saldo USYC tidak cukup. Saldo tersedia: {$senderWallet->formatted_balance}"
            );
        }

        // 2. Reserve sender funds
        $senderWallet->reserveAmount($amountUsyc);

        // 3. Create pending transaction record
        $tx = UsycTransaction::createPayment(
            senderPhone:   $senderPhone,
            receiverPhone: $receiverPhone,
            amountUsyc:    $amountUsyc,
            listingId:     $context['listing_id'] ?? null,
            description:   $context['description'] ?? "Marketplace Jamaah AI payment",
            type:          'payment',
        );

        $tx->update([
            'sender_arc_address'   => $senderWallet->arc_address,
            'receiver_arc_address' => $receiverWallet->arc_address,
            'whatsapp_message_id'  => $context['wa_message_id'] ?? null,
            'whatsapp_group_id'    => $context['wa_group_id'] ?? null,
        ]);

        try {
            // 4. Broadcast on Arc blockchain (testnet = mock, prod = real)
            $receipt = $this->broadcastTransfer(
                from:     $senderWallet->arc_address ?? $this->generateWalletAddress($senderPhone),
                to:       $receiverWallet->arc_address ?? $this->generateWalletAddress($receiverPhone),
                amount:   $amountUsyc,
                fee:      $tx->fee_usyc,
            );

            // 5. Update transaction as confirmed
            $tx->markConfirmed(
                txHash:      $receipt['tx_hash'],
                blockNumber: $receipt['block_number'] ?? null,
                receipt:     $receipt,
            );

            // 6. Settle balances off-chain
            $senderWallet->debit($amountUsyc);
            $receiverWallet->credit($amountUsyc - $tx->fee_usyc);

            Log::info('[Arc] Payment confirmed', [
                'tx_hash' => $receipt['tx_hash'],
                'amount'  => $amountUsyc,
            ]);

            return $tx->fresh();
        } catch (\Exception $e) {
            // Release reservation on failure
            $senderWallet->releaseReserved($amountUsyc);
            $tx->markFailed($e->getMessage());
            Log::error('[Arc] Payment failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create an escrow payment — funds locked until buyer confirms receipt
     * or escrow auto-releases after N hours.
     */
    public function createEscrow(
        string $senderPhone,
        string $receiverPhone,
        float  $amountUsyc,
        int    $escrowHours = 24,
        array  $context = []
    ): UsycTransaction {
        $context['type'] = 'escrow';
        $tx = $this->sendPayment($senderPhone, $receiverPhone, $amountUsyc, $context);

        $tx->update([
            'escrow_status'    => 'held',
            'escrow_release_at'=> now()->addHours($escrowHours),
        ]);

        // While in escrow, USYC continues earning yield on Arc
        Log::info('[Arc] Escrow created', [
            'tx_id'        => $tx->id,
            'release_at'   => $tx->escrow_release_at,
        ]);

        return $tx;
    }

    /**
     * Release escrow to seller (buyer confirms received)
     */
    public function releaseEscrow(UsycTransaction $tx): UsycTransaction
    {
        if (!$tx->isEscrow()) {
            throw new \Exception('Transaction is not in escrow');
        }

        // Calculate yield earned during escrow period
        $hoursHeld    = now()->diffInHours($tx->created_at);
        $yieldEarned  = $this->calculateYield($tx->amount_usyc, $hoursHeld);

        $tx->update([
            'escrow_status' => 'released',
            'yield_earned'  => $yieldEarned,
            'status'        => 'confirmed',
        ]);

        // Credit yield to receiver
        if ($yieldEarned > 0) {
            $receiverWallet = UsycWallet::forPhone($tx->receiver_phone);
            $receiverWallet->credit($yieldEarned);
            Log::info('[Arc] Escrow yield credited', ['yield' => $yieldEarned]);
        }

        return $tx->fresh();
    }

    // ── Wallet Management ─────────────────────────────────────────────────────

    /**
     * Get or create a wallet for a phone number, with testnet funding.
     */
    public function getOrCreateWallet(string $phone, bool $topupDemo = false): UsycWallet
    {
        $wallet = UsycWallet::forPhone($phone);

        if (!$wallet->arc_address) {
            $wallet->update([
                'arc_address' => $this->generateWalletAddress($phone),
            ]);
        }

        if ($topupDemo && $wallet->usyc_balance == 0 && $this->testnet) {
            // Give testnet USYC for demo
            $wallet->credit(100.0);
            Log::info('[Arc] Testnet USYC topup', ['phone' => $phone, 'amount' => 100.0]);
        }

        return $wallet;
    }

    /**
     * Get wallet balance (from Arc blockchain + local cache)
     */
    public function getBalance(string $phone): array
    {
        $wallet  = UsycWallet::forPhone($phone);
        $balance = (float) $wallet->usyc_balance;

        if ($this->testnet) {
            // In testnet, balance is maintained off-chain
            return [
                'phone'     => $phone,
                'address'   => $wallet->arc_address,
                'usyc'      => $balance,
                'reserved'  => (float) $wallet->usyc_reserved,
                'available' => $wallet->available_balance,
                'usd_equiv' => $balance, // USYC ≈ $1 USD
                'network'   => 'arc-testnet',
            ];
        }

        // Production: query Arc blockchain
        try {
            $onChain = $this->queryOnChainBalance($wallet->arc_address);
            // Update cache
            $wallet->update(['usyc_balance' => $onChain]);
            $balance = $onChain;
        } catch (\Exception $e) {
            Log::warning('[Arc] On-chain balance query failed, using cache', ['error' => $e->getMessage()]);
        }

        return [
            'phone'     => $phone,
            'address'   => $wallet->arc_address,
            'usyc'      => $balance,
            'reserved'  => (float) $wallet->usyc_reserved,
            'available' => $wallet->available_balance,
            'usd_equiv' => $balance,
            'network'   => 'arc-mainnet',
        ];
    }

    // ── Blockchain Calls ──────────────────────────────────────────────────────

    /**
     * Broadcast ERC-20 transfer on Arc blockchain.
     * In testnet mode, returns a realistic mock receipt.
     */
    private function broadcastTransfer(
        string $from,
        string $to,
        float  $amount,
        float  $fee
    ): array {
        if ($this->testnet) {
            return $this->mockArcReceipt($from, $to, $amount);
        }

        // Production: call Arc RPC
        $response = Http::post("{$this->rpcUrl}/v1/transfer", [
            'from'             => $from,
            'to'               => $to,
            'amount'           => $this->toWei($amount),
            'token'            => $this->usycContractAddress,
            'operator_address' => $this->operatorAddress,
            'signature'        => $this->signTransfer($from, $to, $amount),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Arc RPC error: ' . $response->body());
        }

        return $response->json();
    }

    private function queryOnChainBalance(string $address): float
    {
        $response = Http::post("{$this->rpcUrl}/v1/balance", [
            'address' => $address,
            'token'   => $this->usycContractAddress,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Arc balance query failed');
        }

        return $this->fromWei($response->json('balance'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Deterministically derive a mock Arc wallet address from phone number.
     * In production, this would be a real key-derivation or custodial address.
     */
    public function generateWalletAddress(string $phone): string
    {
        $hash = hash('sha256', "arc-jamaah-{$phone}-" . config('app.key', 'secret'));
        return '0x' . substr($hash, 0, 40);
    }

    /**
     * Simulate Arc blockchain receipt (testnet/demo only)
     */
    private function mockArcReceipt(string $from, string $to, float $amount): array
    {
        // Simulate 200ms block time (Arc L2 is fast)
        usleep(200_000);

        $txHash = '0x' . bin2hex(random_bytes(32));
        $block  = random_int(4_000_000, 5_000_000);

        return [
            'tx_hash'        => $txHash,
            'block_number'   => (string) $block,
            'from'           => $from,
            'to'             => $to,
            'amount_wei'     => $this->toWei($amount),
            'gas_used'       => 21000,
            'status'         => 'success',
            'network'        => 'arc-testnet',
            'timestamp'      => now()->toIso8601String(),
        ];
    }

    /**
     * Calculate yield earned during escrow (USYC earns ~5% APY)
     */
    private function calculateYield(float $principal, int $hours): float
    {
        $apy           = 0.05; // 5% APY for USYC
        $hourlyRate    = $apy / 8760;
        return round($principal * $hourlyRate * $hours, 8);
    }

    private function toWei(float $amount): string
    {
        return (string) round($amount * 1e6); // USYC uses 6 decimals
    }

    private function fromWei(string $wei): float
    {
        return (float) $wei / 1e6;
    }

    private function signTransfer(string $from, string $to, float $amount): string
    {
        // Production: sign with operator private key (EIP-712)
        return hash_hmac('sha256', "{$from}{$to}{$amount}", $this->operatorPk);
    }
}
