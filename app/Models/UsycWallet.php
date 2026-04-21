<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsycWallet extends Model
{
    protected $fillable = [
        'phone_number',
        'arc_address',
        'usyc_balance',
        'usyc_reserved',
        'status',
        'is_verified',
        'metadata',
        'last_activity_at',
    ];

    protected $casts = [
        'usyc_balance'      => 'decimal:8',
        'usyc_reserved'     => 'decimal:8',
        'is_verified'       => 'boolean',
        'metadata'          => 'array',
        'last_activity_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function sentTransactions(): HasMany
    {
        return $this->hasMany(UsycTransaction::class, 'sender_phone', 'phone_number');
    }

    public function receivedTransactions(): HasMany
    {
        return $this->hasMany(UsycTransaction::class, 'receiver_phone', 'phone_number');
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /**
     * Available balance = total balance minus reserved (locked in escrow)
     */
    public function getAvailableBalanceAttribute(): float
    {
        return max(0, (float) $this->usyc_balance - (float) $this->usyc_reserved);
    }

    /**
     * Formatted: "12.50000000 USYC (~$12.50)"
     */
    public function getFormattedBalanceAttribute(): string
    {
        $usyc = number_format($this->usyc_balance, 2);
        // USYC ≈ 1 USD (yield-bearing)
        return "{$usyc} USYC";
    }

    // ── Business logic ────────────────────────────────────────────────────────

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->available_balance >= $amount;
    }

    public function reserveAmount(float $amount): bool
    {
        if (!$this->hasSufficientBalance($amount)) {
            return false;
        }
        $this->increment('usyc_reserved', $amount);
        $this->touch('last_activity_at');
        return true;
    }

    public function releaseReserved(float $amount): void
    {
        $this->decrement('usyc_reserved', min($amount, $this->usyc_reserved));
    }

    public function debit(float $amount): void
    {
        $this->decrement('usyc_balance', $amount);
        $this->decrement('usyc_reserved', min($amount, $this->usyc_reserved));
        $this->touch('last_activity_at');
    }

    public function credit(float $amount): void
    {
        $this->increment('usyc_balance', $amount);
        $this->touch('last_activity_at');
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    public static function forPhone(string $phone): self
    {
        return static::firstOrCreate(
            ['phone_number' => $phone],
            ['status' => 'active', 'usyc_balance' => 0]
        );
    }

    /**
     * Demo: top up with USYC (for hackathon testnet)
     */
    public static function topUpDemo(string $phone, float $amount = 50.0): self
    {
        $wallet = static::forPhone($phone);
        $wallet->credit($amount);
        return $wallet;
    }
}
