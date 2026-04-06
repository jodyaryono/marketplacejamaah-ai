<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasFactory, SoftDeletes;
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
        'price_type',
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

        $type = $this->price_type ?? 'fix';

        if ($this->price && $this->price > 0) {
            $rp = 'Rp ' . number_format($this->price, 0, ',', '.');
            return match ($type) {
                'nego'   => "{$rp} (Nego)",
                'lelang' => "Lelang mulai {$rp}",
                default  => $rp,
            };
        }

        if ($this->price_min && $this->price_max) {
            $range = 'Rp ' . number_format($this->price_min, 0, ',', '.') . ' - ' . number_format($this->price_max, 0, ',', '.');
            return $type === 'nego' ? "{$range} (Nego)" : $range;
        }

        return match ($type) {
            'nego'   => 'Harga Nego',
            'lelang' => 'Harga Lelang',
            default  => 'Harga tidak dicantumkan',
        };
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
