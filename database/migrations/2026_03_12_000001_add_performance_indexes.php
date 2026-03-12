<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->index('contact_id');
            $table->index('whatsapp_group_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index('whatsapp_group_id');
            $table->index('sender_number');
            $table->index(['is_ad', 'whatsapp_group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['contact_id']);
            $table->dropIndex(['whatsapp_group_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_group_id']);
            $table->dropIndex(['sender_number']);
            $table->dropIndex(['is_ad', 'whatsapp_group_id']);
        });
    }
};
