<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group')->default('general');
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('value')->nullable();
            $table->string('type')->default('text');  // text, textarea, boolean, url
            $table->boolean('is_public')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default settings
        $now = now();
        DB::table('settings')->insert([
            [
                'key' => 'whatsapp_group_link',
                'group' => 'whatsapp',
                'label' => 'Link Grup WhatsApp',
                'description' => 'Link undangan WhatsApp Group marketplace. Dikirim ke user yang belum terdaftar saat DM ke bot.',
                'value' => '',
                'type' => 'url',
                'is_public' => false,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'marketplace_name',
                'group' => 'general',
                'label' => 'Nama Marketplace',
                'description' => 'Nama marketplace yang ditampilkan di pesan bot dan notifikasi.',
                'value' => 'Marketplace Jamaah',
                'type' => 'text',
                'is_public' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'marketplace_url',
                'group' => 'general',
                'label' => 'URL Marketplace',
                'description' => 'URL website marketplace publik.',
                'value' => '',
                'type' => 'url',
                'is_public' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'oneliner_min_words',
                'group' => 'moderation',
                'label' => 'Minimum Kata Iklan (one-liner guard)',
                'description' => 'Pesan dengan jumlah kata di bawah angka ini dan 1 baris akan dihapus dari grup (kecuali ada harga).',
                'value' => '12',
                'type' => 'text',
                'is_public' => false,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
