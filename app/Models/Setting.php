<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'group', 'label', 'description', 'value', 'type', 'is_public', 'sort_order'];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get a setting value by key, with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $val = Cache::remember("setting:{$key}", 300, function () use ($key) {
            return static::where('key', $key)->value('value');
        });
        return $val ?? $default;
    }

    /**
     * Set a setting value, clears cache.
     */
    public static function set(string $key, mixed $value): void
    {
        static::where('key', $key)->update(['value' => $value]);
        Cache::forget("setting:{$key}");
    }
}
