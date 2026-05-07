<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Backfill ai_models.api_key for rows that the previous migration seeded
 * with NULL — caused by env() returning empty after `config:cache` was
 * already in effect. config() reads the cached config so it works.
 *
 * Idempotent: only updates rows whose api_key is currently empty.
 */
return new class extends Migration {
    public function up(): void
    {
        $geminiKey = config('services.gemini.api_key');
        $groqKey   = config('services.groq.api_key');

        if (!empty($geminiKey)) {
            $enc = Crypt::encryptString((string) $geminiKey);
            DB::table('ai_models')
                ->where('provider', 'gemini')
                ->where(function ($q) {
                    $q->whereNull('api_key')->orWhere('api_key', '');
                })
                ->update(['api_key' => $enc, 'updated_at' => now()]);
        }

        if (!empty($groqKey)) {
            $enc = Crypt::encryptString((string) $groqKey);
            DB::table('ai_models')
                ->whereIn('provider', ['groq', 'openai', 'openrouter'])
                ->where(function ($q) {
                    $q->whereNull('api_key')->orWhere('api_key', '');
                })
                ->update(['api_key' => $enc, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // No-op: we don't unseed keys on rollback.
    }
};
