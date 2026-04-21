<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Listing;
use App\Models\UsycTransaction;
use App\Models\UsycWallet;
use App\Services\ArcPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PaymentController
 *
 * REST API endpoints for USYC nanopayment operations.
 * Used by the Python AI agent and the frontend dashboard.
 */
class PaymentController extends Controller
{
    public function __construct(private ArcPaymentService $arc) {}

    // ── Wallet ────────────────────────────────────────────────────────────────

    /**
     * GET /api/usyc/wallet/{phone}
     * Get or create wallet for a phone number.
     */
    public function wallet(string $phone): JsonResponse
    {
        $wallet = $this->arc->getOrCreateWallet($phone);

        return response()->json([
            'phone'          => $phone,
            'arc_address'    => $wallet->arc_address,
            'usyc_balance'   => (float) $wallet->usyc_balance,
            'usyc_reserved'  => (float) $wallet->usyc_reserved,
            'available'      => $wallet->available_balance,
            'formatted'      => $wallet->formatted_balance,
            'is_verified'    => $wallet->is_verified,
            'status'         => $wallet->status,
            'last_activity'  => $wallet->last_activity_at?->toIso8601String(),
        ]);
    }

    /**
     * GET /api/usyc/balance/{phone}
     * Get live balance (queries Arc blockchain).
     */
    public function balance(string $phone): JsonResponse
    {
        $balance = $this->arc->getBalance($phone);
        return response()->json($balance);
    }

    /**
     * POST /api/usyc/wallet/topup-demo
     * Demo: Add testnet USYC to a wallet.
     */
    public function topupDemo(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string', 'amount' => 'nullable|numeric|min:0.01|max:1000']);

        $phone  = $request->input('phone');
        $amount = (float) $request->input('amount', 50.0);

        $wallet = UsycWallet::topUpDemo($phone, $amount);

        return response()->json([
            'success'    => true,
            'phone'      => $phone,
            'credited'   => $amount,
            'new_balance'=> (float) $wallet->usyc_balance,
            'message'    => "Testnet: {$amount} USYC telah ditambahkan ke wallet.",
        ]);
    }

    // ── Payments ──────────────────────────────────────────────────────────────

    /**
     * POST /api/usyc/pay
     * Initiate a USYC payment (buyer → seller, with escrow).
     */
    public function pay(Request $request): JsonResponse
    {
        $request->validate([
            'sender_phone'   => 'required|string',
            'receiver_phone' => 'required|string',
            'amount_usyc'    => 'required|numeric|min:0.001',
            'listing_id'     => 'nullable|integer|exists:listings,id',
            'use_escrow'     => 'nullable|boolean',
            'escrow_hours'   => 'nullable|integer|min:1|max:168',
        ]);

        try {
            $useEscrow = $request->boolean('use_escrow', true);
            $context   = [
                'listing_id'  => $request->input('listing_id'),
                'description' => $request->input('description', 'Marketplace Jamaah AI payment'),
            ];

            if ($useEscrow) {
                $tx = $this->arc->createEscrow(
                    senderPhone:   $request->input('sender_phone'),
                    receiverPhone: $request->input('receiver_phone'),
                    amountUsyc:    (float) $request->input('amount_usyc'),
                    escrowHours:   (int)   $request->input('escrow_hours', 24),
                    context:       $context,
                );
            } else {
                $tx = $this->arc->sendPayment(
                    senderPhone:   $request->input('sender_phone'),
                    receiverPhone: $request->input('receiver_phone'),
                    amountUsyc:    (float) $request->input('amount_usyc'),
                    context:       $context,
                );
            }

            return response()->json([
                'success'        => true,
                'tx_id'          => $tx->id,
                'tx_hash'        => $tx->tx_hash,
                'amount_usyc'    => (float) $tx->amount_usyc,
                'fee_usyc'       => (float) $tx->fee_usyc,
                'status'         => $tx->status,
                'escrow'         => $useEscrow,
                'escrow_status'  => $tx->escrow_status,
                'escrow_release' => $tx->escrow_release_at?->toIso8601String(),
                'yield_preview'  => round((float) $tx->amount_usyc * 0.05 / 365, 6),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/usyc/payment-confirmed
     * Called by Python AI agent after blockchain confirmation.
     */
    public function paymentConfirmed(Request $request): JsonResponse
    {
        $request->validate([
            'sender_phone'   => 'required|string',
            'receiver_phone' => 'required|string',
            'amount_usyc'    => 'required|numeric',
            'tx_hash'        => 'required|string',
        ]);

        $tx = UsycTransaction::where('tx_hash', $request->input('tx_hash'))->first()
            ?? UsycTransaction::createPayment(
                senderPhone:   $request->input('sender_phone'),
                receiverPhone: $request->input('receiver_phone'),
                amountUsyc:    $request->input('amount_usyc'),
                listingId:     $request->input('listing_id'),
                description:   'Confirmed via Python AI Agent',
            );

        $tx->markConfirmed($request->input('tx_hash'));

        if ($request->input('escrow_status') === 'held') {
            $tx->update(['escrow_status' => 'held', 'escrow_release_at' => now()->addHours(24)]);
        }

        return response()->json(['success' => true, 'tx_id' => $tx->id]);
    }

    /**
     * POST /api/usyc/escrow/{tx}/release
     * Release escrow to seller.
     */
    public function releaseEscrow(UsycTransaction $tx): JsonResponse
    {
        try {
            $tx = $this->arc->releaseEscrow($tx);
            return response()->json([
                'success'      => true,
                'tx_id'        => $tx->id,
                'yield_earned' => (float) $tx->yield_earned,
                'status'       => $tx->escrow_status,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ── Listings ──────────────────────────────────────────────────────────────

    /**
     * GET /api/usyc/listings
     * Get listings that accept USYC payment.
     */
    public function listings(Request $request): JsonResponse
    {
        $query = Listing::where('status', 'active')
            ->where('accepts_usyc', true)
            ->with(['contact', 'category']);

        if ($request->has('group_id')) {
            $query->whereHas('group', fn ($q) => $q->where('group_id', $request->input('group_id')));
        }

        $listings = $query->latest()->limit(50)->get()->map(fn ($l) => [
            'id'           => $l->id,
            'title'        => $l->title,
            'description'  => $l->description,
            'price_usyc'   => (float) $l->price_usyc,
            'price_idr'    => $l->price_formatted,
            'accepts_usyc' => true,
            'seller_phone' => $l->contact_number,
            'seller_name'  => $l->contact?->getSapaan(),
            'category'     => $l->category?->name,
            'status'       => $l->status,
        ]);

        return response()->json(['listings' => $listings, 'total' => $listings->count()]);
    }

    /**
     * PATCH /api/usyc/listings/{listing}/enable
     * Enable USYC payment for a listing.
     */
    public function enableUsyc(Request $request, Listing $listing): JsonResponse
    {
        $request->validate(['price_usyc' => 'required|numeric|min:0.001']);

        $listing->update([
            'accepts_usyc' => true,
            'price_usyc'   => $request->input('price_usyc'),
        ]);

        return response()->json([
            'success'    => true,
            'listing_id' => $listing->id,
            'price_usyc' => (float) $listing->price_usyc,
        ]);
    }

    // ── Transactions ──────────────────────────────────────────────────────────

    /**
     * GET /api/usyc/transactions/{phone}
     * Get transaction history for a phone number.
     */
    public function transactions(string $phone): JsonResponse
    {
        $sent = UsycTransaction::where('sender_phone', $phone)
            ->latest()
            ->limit(20)
            ->get();

        $received = UsycTransaction::where('receiver_phone', $phone)
            ->latest()
            ->limit(20)
            ->get();

        $all = $sent->merge($received)->sortByDesc('created_at')->take(30)->values();

        $formatted = $all->map(fn ($tx) => [
            'id'            => $tx->id,
            'tx_hash'       => $tx->tx_hash,
            'type'          => $tx->tx_type,
            'direction'     => $tx->sender_phone === $phone ? 'sent' : 'received',
            'amount_usyc'   => (float) $tx->amount_usyc,
            'fee_usyc'      => (float) $tx->fee_usyc,
            'yield_earned'  => (float) $tx->yield_earned,
            'status'        => $tx->status,
            'escrow_status' => $tx->escrow_status,
            'description'   => $tx->description,
            'counterparty'  => $tx->sender_phone === $phone ? $tx->receiver_phone : $tx->sender_phone,
            'listing_id'    => $tx->listing_id,
            'listing_title' => $tx->listing?->title,
            'created_at'    => $tx->created_at?->toIso8601String(),
        ]);

        $totalYield = $all->sum('yield_earned');

        return response()->json([
            'phone'        => $phone,
            'transactions' => $formatted,
            'total_count'  => $formatted->count(),
            'yield_total'  => round($totalYield, 6),
        ]);
    }

    // ── Dashboard Stats ───────────────────────────────────────────────────────

    /**
     * GET /api/usyc/stats
     * Platform-wide USYC payment statistics.
     */
    public function stats(): JsonResponse
    {
        $totalTx      = UsycTransaction::where('status', 'confirmed')->count();
        $totalVolume  = UsycTransaction::where('status', 'confirmed')->sum('amount_usyc');
        $totalEscrow  = UsycTransaction::where('escrow_status', 'held')->sum('amount_usyc');
        $totalYield   = UsycTransaction::sum('yield_earned');
        $activeWallets= UsycWallet::count();

        return response()->json([
            'total_transactions' => $totalTx,
            'total_volume_usyc'  => round($totalVolume, 2),
            'total_escrow_usyc'  => round($totalEscrow, 2),
            'total_yield_usyc'   => round($totalYield, 6),
            'active_wallets'     => $activeWallets,
            'nanopayment_count'  => UsycTransaction::where('amount_usyc', '<', 1.0)->count(),
            'network'            => config('arc.testnet', true) ? 'arc-testnet' : 'arc-mainnet',
        ]);
    }
}
