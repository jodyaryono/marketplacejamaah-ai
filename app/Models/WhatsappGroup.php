<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class WhatsappGroup extends Model
{
    protected $fillable = [
        'group_id',
        'group_name',
        'description',
        'phone_number',
        'is_active',
        'message_count',
        'ad_count',
        'admin_count',
        'only_admins_can_send',
        'last_message_at',
        'participants_raw',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'only_admins_can_send' => 'boolean',
        'last_message_at' => 'datetime',
        'participants_raw' => 'array',
    ];

    /**
     * Contacts who have sent at least one message in this group.
     */
    public function members()
    {
        return \App\Models\Contact::whereHas('messages', fn($q) =>
            $q->where('whatsapp_group_id', $this->id));
    }

    /**
     * Count of distinct senders in this group (from messages).
     */
    public function getMemberCountAttribute(): int
    {
        return \App\Models\Message::where('whatsapp_group_id', $this->id)
            ->distinct('sender_number')
            ->count('sender_number');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(AnalyticsDaily::class);
    }
}
