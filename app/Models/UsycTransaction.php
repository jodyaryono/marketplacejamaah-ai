<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UsycTransaction extends Model
{
    protected $fillable = [
        'tx_hash',
        'tx_type',
        'sender_phone',
        'receiver_phone',
        'sender_arc_address',
        'receiver_arc_address',
        'amount_usyc',
        'amount_usd',
        'fee_usyc',
        'yield_earned',
        'status',
        'confirmations',
        'required_confirmations',
        'listing_id',
        'whatsapp_message_id',
        'whatsapp_group_id',
        'description',
        'metadata',
        'arc_block_number',
        'arc_confirmed_at',
        'arc_raw_receipt',
        'escrow_release_at',
        'escrow_status',
    ];

    protected $casts = [
        'amount_usyc'         => 'decimal:8',
        'amount_usd'          => 'decimal:8',
        'fee_usyc'            => 'decimal:8',
        'yield_earned'        => 'decimal:8',
        'metadata'            => 'array',
        'arc_raw_receipt'     => 'array',
        'arc_confirmed_at'    => 'datetime',
        'escrow_release_at'   => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isConfirmed(): bool { return $this->status === 'confirmed'; }
    public function isFailed(): bool    { return $this->status === 'failed'; }
    public function isEscrow(): bool    { return $this->escrow_status === 'held'; }

    public function markConfirmed(string $txHash, ?string $blockNumber = null, ?array $receipt = null): void
    {
        $this->update([
            'status'           => 'confirmed',
            'tx_hash'          => $txHash,
            'arc_block_number' => $blockNumber,
            'arc_confirmed_at' => now(),
            'arc_raw_receipt'  => $receipt,
            'confirmations'    => 1,
        ]);
    }

    public function markFailed(string $reason = ''): void
    {
        $this->update([
            'status'   => 'failed',
            'metadata' => array_merge($this->metadata ?? [], ['failure_reason' => $reason]),
        ]);
    }

    // ── Factory helper ────────────────────────────────────────────────────────

    public static function createPayment(
        string $senderPhone,
        string $receiverPhone,
        float  $amountUsyc,
        ?int   $listingId   = null,
        string $description = '',
        string $type        = 'payment',
    ): self {
        return static::create([
            'tx_hash'        => null, // filled after blockchain confirmation
            'tx_type'        => $type,
            'sender_phone'   => $senderPhone,
            'receiver_phone' => $receiverPhone,
            'amount_usyc'    => $amountUsyc,
            'amount_usd'     => $amountUsyc, // USYC ≈ 1 USD
            'fee_usyc'       => round($amountUsyc * 0.001, 8), // 0.1% platform fee
            'status'         => 'pending',
            'listing_id'     => $listingId,
            'description'    => $description,
        ]);
    }
}
