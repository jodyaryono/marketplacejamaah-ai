<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use App\Models\Contact;

class Message extends Model
{
    protected $fillable = [
        'whatsapp_group_id',
        'message_id',
        'sender_number',
        'sender_name',
        'message_type',
        'raw_body',
        'media_url',
        'media_filename',
        'direction',
        'recipient_number',
        'is_processed',
        'is_ad',
        'ad_confidence',
        'processed_at',
        'sent_at',
        'raw_payload',
        'message_category',
        'violation_detected',
        'moderation_result',
    ];

    protected $casts = [
        'is_processed' => 'boolean',
        'is_ad' => 'boolean',
        'ad_confidence' => 'decimal:2',
        'processed_at' => 'datetime',
        'sent_at' => 'datetime',
        'raw_payload' => 'array',
        'violation_detected' => 'boolean',
        'moderation_result' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(WhatsappGroup::class, 'whatsapp_group_id');
    }

    public function listing(): HasOne
    {
        return $this->hasOne(Listing::class);
    }

    public function agentLogs(): HasMany
    {
        return $this->hasMany(AgentLog::class);
    }

    public function senderContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'sender_number', 'phone_number');
    }

    public function recipientContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'recipient_number', 'phone_number');
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    public function scopeAds($query)
    {
        return $query->where('is_ad', true);
    }
}
