<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class Setting extends Model
{
    protected $fillable = ['key', 'group', 'label', 'description', 'value', 'type', 'is_public', 'is_secret', 'sort_order'];

    protected $casts = [
        'is_public' => 'boolean',
        'is_secret' => 'boolean',
    ];

    /**
     * Get a setting value by key, with optional default.
     * Transparently decrypts when the row is marked is_secret=true.
     * Returns $default if value is empty/null or decryption fails.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = Cache::remember("setting:{$key}", 300, function () use ($key) {
            return static::where('key', $key)->first(['key', 'value', 'is_secret']);
        });
        if (!$row) return $default;

        $value = $row->value;
        if ($value === null || $value === '') return $default;

        if ($row->is_secret) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                Log::warning("Setting::get failed to decrypt '{$key}' — APP_KEY changed?", ['error' => $e->getMessage()]);
                return $default;
            }
        }
        return $value;
    }

    /**
     * Set a setting value, clears cache.
     * Transparently encrypts when the row is marked is_secret=true.
     */
    public static function set(string $key, mixed $value): void
    {
        $row = static::where('key', $key)->first();
        if (!$row) return;

        $store = $value;
        if ($row->is_secret && $value !== null && $value !== '') {
            $store = Crypt::encryptString((string) $value);
        }

        static::where('key', $key)->update(['value' => $store]);
        Cache::forget("setting:{$key}");
    }

    /**
     * Mask a secret value for display (first 6 + last 4, stars in between).
     * Returns '(belum diisi)' for empty values.
     */
    public static function masked(?string $raw, string $emptyLabel = '(belum diisi)'): string
    {
        if ($raw === null || $raw === '') return $emptyLabel;
        $len = strlen($raw);
        if ($len <= 10) return str_repeat('*', $len);
        return substr($raw, 0, 6) . str_repeat('*', $len - 10) . substr($raw, -4);
    }
}
