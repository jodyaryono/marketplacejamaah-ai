<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'phone_number',
        'name',
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

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_number', 'phone_number');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
