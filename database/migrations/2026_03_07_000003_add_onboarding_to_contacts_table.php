<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_registered')->default(false)->after('last_seen');
            $table->enum('onboarding_status', ['pending', 'completed', 'skipped'])->nullable()->after('is_registered');
            $table->enum('member_role', ['seller', 'buyer', 'both'])->nullable()->after('onboarding_status');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['is_registered', 'onboarding_status', 'member_role']);
        });
    }
};
