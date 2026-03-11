<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AnalyticsDaily extends Model
{
    protected $table = 'analytics_daily';

    protected $fillable = [
        'date',
        'whatsapp_group_id',
        'total_messages',
        'total_ads',
        'total_contacts',
        'total_listings',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(WhatsappGroup::class, 'whatsapp_group_id');
    }
}
