<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AgentLog extends Model
{
    protected $fillable = [
        'agent_name',
        'message_id',
        'input_payload',
        'output_payload',
        'status',
        'error',
        'duration_ms',
        'retry_count',
    ];

    protected $casts = [
        'input_payload' => 'array',
        'output_payload' => 'array',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
