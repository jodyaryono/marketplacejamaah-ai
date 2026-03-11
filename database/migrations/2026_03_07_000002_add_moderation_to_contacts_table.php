<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedInteger('warning_count')->default(0)->after('ad_count');
            $table->unsignedInteger('total_violations')->default(0)->after('warning_count');
            $table->boolean('is_blocked')->default(false)->after('total_violations');
            $table->timestamp('last_warning_at')->nullable()->after('is_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['warning_count', 'total_violations', 'is_blocked', 'last_warning_at']);
        });
    }
};
