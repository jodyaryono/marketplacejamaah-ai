<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_messages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Identifier unik, contoh: onboarding.welcome');
            $table->string('group')->default('general')->comment('Grup kategori pesan');
            $table->string('label')->comment('Nama tampilan di dashboard');
            $table->text('description')->nullable()->comment('Penjelasan kapan pesan ini dikirim');
            $table->text('body')->comment('Isi pesan — mendukung emoji & WhatsApp markdown (*bold*, _italic_)');
            $table->json('placeholders')->nullable()->comment('Daftar placeholder yang tersedia, misal: ["name","phone","role"]');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_messages');
    }
};
