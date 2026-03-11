<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        DB::table('settings')->insert([
            'key' => 'warning_reset_days',
            'group' => 'moderation',
            'label' => 'Reset Peringatan Setelah (Hari)',
            'description' => 'Jumlah hari setelah peringatan terakhir sebelum warning_count di-reset ke 0. Contoh: 1 = peringatan direset setelah 1 hari tidak melanggar.',
            'value' => '1',
            'type' => 'text',
            'is_public' => false,
            'sort_order' => 21,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'warning_reset_days')->delete();
    }
};
