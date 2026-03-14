<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Listing extends Model
{
    protected $fillable = [
        'message_id',
        'whatsapp_group_id',
        'category_id',
        'contact_id',
        'title',
        'description',
        'price',
        'price_min',
        'price_max',
        'price_label',
        'contact_number',
        'contact_name',
        'media_urls',
        'gdrive_url',
        'location',
        'condition',
        'status',
        'source_date',
    ];

    protected $casts = [
        'media_urls' => 'array',
        'source_date' => 'datetime',
        'price' => 'decimal:2',
        'price_min' => 'decimal:2',
        'price_max' => 'decimal:2',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WhatsappGroup::class, 'whatsapp_group_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function getPriceFormattedAttribute(): string
    {
        if ($this->price_label) {
            return $this->price_label;
        }
        if ($this->price) {
            return 'Rp ' . number_format($this->price, 0, ',', '.');
        }
        if ($this->price_min && $this->price_max) {
            return 'Rp ' . number_format($this->price_min, 0, ',', '.') . ' - ' . number_format($this->price_max, 0, ',', '.');
        }
        return 'Harga tidak dicantumkan';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
