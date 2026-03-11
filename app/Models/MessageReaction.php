<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class MessageReaction extends Model
{
    protected $fillable = [
        'message_id',
        'target_message_id',
        'sender_number',
        'sender_name',
        'emoji',
        'whatsapp_group_id',
        'raw_payload',
        'reacted_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'reacted_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WhatsappGroup::class, 'whatsapp_group_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'sender_number', 'phone_number');
    }
}
