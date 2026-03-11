<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        DB::table('settings')->insert([
            [
                'key' => 'hadith_enabled',
                'group' => 'hadith',
                'label' => 'Aktifkan Hadits Harian',
                'description' => 'Kirim hadits tentang adab jual beli ke grup WhatsApp secara otomatis.',
                'value' => 'true',
                'type' => 'boolean',
                'is_public' => false,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hadith_times',
                'group' => 'hadith',
                'label' => 'Jam Kirim Hadits',
                'description' => 'Jam pengiriman hadits harian (format HH:MM, pisahkan dengan koma untuk beberapa waktu). Contoh: 05:45 atau 05:45,18:00',
                'value' => '05:45',
                'type' => 'text',
                'is_public' => false,
                'sort_order' => 31,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['hadith_enabled', 'hadith_times'])->delete();
    }
};
