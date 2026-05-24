<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // WhatsApp pushname — name the user set on their own WA account.
            // Stored separately so the onboarding-extracted "name" never gets
            // overwritten, but we still keep the latest pushname for sapaan.
            $table->string('pushname')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('pushname');
        });
    }
};
