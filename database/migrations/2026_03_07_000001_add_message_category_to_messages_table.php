<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('message_category', 50)->nullable()->after('is_processed');
            $table->boolean('violation_detected')->default(false)->after('message_category');
            $table->json('moderation_result')->nullable()->after('violation_detected');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['message_category', 'violation_detected', 'moderation_result']);
        });
    }
};
