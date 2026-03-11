<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemMessage extends Model
{
    protected $fillable = [
        'key',
        'group',
        'label',
        'description',
        'body',
        'media_url',
        'media_type',
        'placeholders',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Ambil teks pesan berdasarkan key, ganti placeholder, dan kembalikan string siap kirim.
     *
     * @param  string  $key       Contoh: 'onboarding.welcome'
     * @param  array   $replace   Contoh: ['name' => 'Jody', 'phone' => '628xxx']
     * @param  string  $default   Fallback jika key tidak ditemukan / tidak aktif
     */
    public static function getText(string $key, array $replace = [], string $default = ''): string
    {
        $msg = static::where('key', $key)->where('is_active', true)->first();
        $body = $msg ? $msg->body : $default;

        foreach ($replace as $placeholder => $value) {
            $body = str_replace('{' . $placeholder . '}', $value, $body);
        }

        return $body;
    }

    /**
     * Return ['text' => ..., 'media_url' => ..., 'media_type' => ...] for a key.
     * Text has placeholders replaced. media_url/media_type are null if not set.
     */
    public static function getPayload(string $key, array $replace = [], string $default = ''): array
    {
        $msg = static::where('key', $key)->where('is_active', true)->first();
        $body = $msg ? $msg->body : $default;

        foreach ($replace as $placeholder => $value) {
            $body = str_replace('{' . $placeholder . '}', $value, $body);
        }

        return [
            'text' => $body,
            'media_url' => $msg?->media_url,
            'media_type' => $msg?->media_type ?? ($msg?->media_url ? 'image' : null),
        ];
    }
}
