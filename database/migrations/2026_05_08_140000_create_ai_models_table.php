<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // human label (e.g. "Gemini Flash — Primary Text")
            $table->string('provider');                   // gemini, groq, openai, anthropic, openrouter, custom
            $table->string('model');                      // model name string sent to the API
            $table->text('api_key')->nullable();          // encrypted at rest
            $table->string('endpoint')->nullable();       // optional override of the default per-provider endpoint
            $table->string('role')->default('primary_text');
            // Roles: primary_text, fallback_text, primary_vision, fallback_vision, disabled
            $table->integer('priority')->default(10);     // lower = tried first within same role
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['role', 'priority', 'is_active']);
        });

        // Backfill defaults — pull from previous Setting rows if user already filled them,
        // otherwise from .env, otherwise leave api_key null.
        $now = now();

        // Use config() not env() — env() returns empty after config:cache.
        $pull = function (string $settingKey, ?string $configFallback) {
            try {
                $v = DB::table('settings')->where('key', $settingKey)->value('value');
                if (!empty($v)) {
                    // Decrypt if it was stored encrypted
                    try { return Crypt::decryptString($v); } catch (\Throwable $e) { return $v; }
                }
            } catch (\Throwable $e) {}
            return $configFallback;
        };

        $geminiKey       = $pull('gemini_api_key',     config('services.gemini.api_key'));
        $geminiModel     = $pull('gemini_model',       config('services.gemini.model')) ?: 'gemini-flash-latest';
        $groqKey         = $pull('groq_api_key',       config('services.groq.api_key'));
        $groqText        = $pull('groq_model',         config('services.groq.model')) ?: 'llama-3.3-70b-versatile';
        $groqVision      = $pull('groq_vision_model',  config('services.groq.vision_model')) ?: 'meta-llama/llama-4-scout-17b-16e-instruct';

        $enc = fn($v) => empty($v) ? null : Crypt::encryptString((string) $v);

        $rows = [
            [
                'name' => 'Gemini Flash — Text',
                'provider' => 'gemini',
                'model' => $geminiModel,
                'api_key' => $enc($geminiKey),
                'endpoint' => null,
                'role' => 'primary_text',
                'priority' => 10,
                'is_active' => true,
                'notes' => 'Default Gemini text model untuk semua agent (klasifikasi, ekstraksi, balasan bot).',
            ],
            [
                'name' => 'Gemini Flash — Vision',
                'provider' => 'gemini',
                'model' => $geminiModel,
                'api_key' => $enc($geminiKey),
                'endpoint' => null,
                'role' => 'primary_vision',
                'priority' => 10,
                'is_active' => true,
                'notes' => 'Default Gemini vision untuk analisis gambar (KTP scan, image analyzer).',
            ],
            [
                'name' => 'Groq Llama 3.3 — Text Fallback',
                'provider' => 'groq',
                'model' => $groqText,
                'api_key' => $enc($groqKey),
                'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
                'role' => 'fallback_text',
                'priority' => 20,
                'is_active' => true,
                'notes' => 'Fallback teks ketika Gemini gagal / circuit breaker terbuka.',
            ],
            [
                'name' => 'Llama 4 Scout — Vision Fallback',
                'provider' => 'groq',
                'model' => $groqVision,
                'api_key' => $enc($groqKey),
                'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
                'role' => 'fallback_vision',
                'priority' => 20,
                'is_active' => true,
                'notes' => 'Fallback vision ketika Gemini vision gagal.',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('ai_models')->insert(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Drop the legacy settings rows; the registry replaces them.
        try {
            DB::table('settings')->whereIn('key', [
                'gemini_api_key', 'gemini_model',
                'groq_api_key', 'groq_model', 'groq_vision_model',
            ])->delete();
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
