<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        DB::table('settings')->insert([
            [
                'key' => 'landing_listings_with_media',
                'group' => 'landing',
                'label' => 'Jumlah Iklan dengan Media (Landing)',
                'description' => 'Jumlah iklan dengan foto/video yang ditampilkan di landing page.',
                'value' => '6',
                'type' => 'number',
                'is_public' => false,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'landing_listings_text',
                'group' => 'landing',
                'label' => 'Jumlah Iklan Barus (Tanpa Media)',
                'description' => 'Jumlah iklan barus (tanpa foto/video) yang ditampilkan di landing page.',
                'value' => '10',
                'type' => 'number',
                'is_public' => false,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['landing_listings_with_media', 'landing_listings_text'])->delete();
    }
};
