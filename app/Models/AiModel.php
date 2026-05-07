<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class AiModel extends Model
{
    protected $fillable = [
        'name', 'provider', 'model', 'api_key', 'endpoint',
        'role', 'priority', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public const ROLES = [
        'primary_text'    => 'Primary text',
        'fallback_text'   => 'Fallback text',
        'primary_vision'  => 'Primary vision',
        'fallback_vision' => 'Fallback vision',
        'disabled'        => 'Disabled',
    ];

    public const PROVIDERS = [
        'gemini'     => 'Google Gemini',
        'groq'       => 'Groq (OpenAI-compatible)',
        'openai'     => 'OpenAI',
        'anthropic'  => 'Anthropic Claude',
        'openrouter' => 'OpenRouter',
        'custom'     => 'Custom (raw HTTP)',
    ];

    /**
     * Encrypt api_key on write.
     */
    public function setApiKeyAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['api_key'] = null;
            return;
        }
        // If already an encrypted blob (e.g. from migration), keep as-is.
        try {
            Crypt::decryptString($value);
            $this->attributes['api_key'] = $value;
            return;
        } catch (\Throwable $e) {
            // not encrypted yet — encrypt
        }
        $this->attributes['api_key'] = Crypt::encryptString((string) $value);
    }

    /**
     * Decrypt api_key on read.
     * Returns null if the row has no key OR decryption fails.
     */
    public function getApiKeyAttribute($value): ?string
    {
        if (empty($value)) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            Log::warning("AiModel::api_key decrypt failed for id={$this->id}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Mask the decrypted key for safe display.
     */
    public function getMaskedKeyAttribute(): string
    {
        $raw = $this->api_key;
        if (empty($raw)) return '(belum diisi)';
        $len = strlen($raw);
        if ($len <= 10) return str_repeat('*', $len);
        return substr($raw, 0, 6) . str_repeat('*', $len - 10) . substr($raw, -4);
    }

    /**
     * Resolve the highest-priority active model for a given role, with .env fallback.
     * Cached for 60s so the queue worker doesn't slam the DB on every API call.
     */
    public static function resolve(string $role): ?self
    {
        return Cache::remember("ai_model:resolve:{$role}", 60, function () use ($role) {
            return static::where('role', $role)
                ->where('is_active', true)
                ->orderBy('priority')
                ->orderByDesc('updated_at')
                ->first();
        });
    }

    /**
     * All active models for a role, in priority order. Used by the failover
     * walker in GeminiService — try each in order until one succeeds.
     */
    public static function activeByRole(string $role): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember("ai_model:list:{$role}", 60, function () use ($role) {
            return static::where('role', $role)
                ->where('is_active', true)
                ->orderBy('priority')
                ->orderByDesc('updated_at')
                ->get();
        });
    }

    /**
     * Bust all caches when any row changes.
     */
    protected static function booted(): void
    {
        $bust = function () {
            foreach (array_keys(self::ROLES) as $role) {
                Cache::forget("ai_model:resolve:{$role}");
                Cache::forget("ai_model:list:{$role}");
            }
        };
        static::saved($bust);
        static::deleted($bust);
    }
}
