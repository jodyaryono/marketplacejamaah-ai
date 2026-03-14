<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'phone_number',
        'name',
        'honorific',
        'avatar',
        'message_count',
        'ad_count',
        'last_seen',
        'warning_count',
        'total_violations',
        'is_blocked',
        'last_warning_at',
        'is_registered',
        'onboarding_status',
        'member_role',
        'sell_products',
        'buy_products',
        'address',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'is_blocked' => 'boolean',
        'last_warning_at' => 'datetime',
        'is_registered' => 'boolean',
    ];

    /**
     * Returns the honorific title: pak, bu, mas, mbak, or kak (default).
     */
    public function getHonorific(): string
    {
        return $this->honorific ?? 'Kak';
    }

    /**
     * Returns "Pak Ahmad", "Mbak Siti", or just "Kak" when name is unknown.
     */
    public function getSapaan(?string $fallbackName = null): string
    {
        $rawName = $this->name ?? $fallbackName;
        $isPhone = !$rawName || preg_match('/^\+?[\d\s\-]{7,}$/', $rawName);
        $name = $isPhone ? null : $rawName;
        $honorific = ucfirst($this->getHonorific());
        return $name ? "{$honorific} {$name}" : $honorific;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_number', 'phone_number');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
