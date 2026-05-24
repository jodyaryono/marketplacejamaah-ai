<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'phone_number',
        'name',
        'pushname',
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
        // Prefer WA pushname when contact.name looks like an old phone-book entry —
        // i.e. has a comma + title fragment ("Darwo Maryono, CDAI" / ", S.E." / ", M.M.")
        // — because that's almost certainly what the person actually calls themselves.
        $messyNamePattern = '/,\s*[A-Z][A-Za-z.\s]{1,12}$/';
        $rawName = $this->name ?? $fallbackName;
        if ($this->pushname && $rawName && preg_match($messyNamePattern, $rawName)) {
            $rawName = $this->pushname;
        }
        if (!$rawName && $this->pushname) {
            $rawName = $this->pushname;
        }
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
