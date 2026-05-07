<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add is_secret flag so model can encrypt/decrypt sensitive values transparently.
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'is_secret')) {
                $table->boolean('is_secret')->default(false)->after('is_public');
            }
        });

        $now = now();
        $rows = [
            [
                'key' => 'gemini_api_key',
                'group' => 'ai',
                'label' => 'Gemini API Key',
                'description' => 'Google AI Studio API key. Disimpan terenkripsi. Kosongkan saat menyimpan untuk mempertahankan nilai sebelumnya. Jika tabel kosong, fallback ke nilai env GEMINI_API_KEY.',
                'value' => null,
                'type' => 'secret',
                'is_public' => false,
                'is_secret' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'gemini_model',
                'group' => 'ai',
                'label' => 'Gemini Model',
                'description' => 'Mis. gemini-flash-latest, gemini-2.5-flash, gemini-2.0-flash. Lihat ai.google.dev/models untuk daftar.',
                'value' => null,
                'type' => 'text',
                'is_public' => false,
                'is_secret' => false,
                'sort_order' => 2,
            ],
            [
                'key' => 'groq_api_key',
                'group' => 'ai',
                'label' => 'Groq API Key (fallback)',
                'description' => 'Dipakai sebagai fallback bila Gemini gagal/circuit terbuka. Disimpan terenkripsi.',
                'value' => null,
                'type' => 'secret',
                'is_public' => false,
                'is_secret' => true,
                'sort_order' => 3,
            ],
            [
                'key' => 'groq_model',
                'group' => 'ai',
                'label' => 'Groq Text Model',
                'description' => 'Mis. llama-3.3-70b-versatile, llama-3.1-8b-instant.',
                'value' => null,
                'type' => 'text',
                'is_public' => false,
                'is_secret' => false,
                'sort_order' => 4,
            ],
            [
                'key' => 'groq_vision_model',
                'group' => 'ai',
                'label' => 'Groq Vision Model (fallback)',
                'description' => 'Mis. meta-llama/llama-4-scout-17b-16e-instruct.',
                'value' => null,
                'type' => 'text',
                'is_public' => false,
                'is_secret' => false,
                'sort_order' => 5,
            ],
        ];

        foreach ($rows as $row) {
            // upsert: insert if missing, do nothing if key already exists
            $exists = DB::table('settings')->where('key', $row['key'])->exists();
            if (!$exists) {
                DB::table('settings')->insert(array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'gemini_api_key', 'gemini_model',
            'groq_api_key', 'groq_model', 'groq_vision_model',
        ])->delete();

        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'is_secret')) {
                $table->dropColumn('is_secret');
            }
        });
    }
};
