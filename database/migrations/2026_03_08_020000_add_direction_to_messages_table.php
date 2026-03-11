<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->enum('direction', ['in', 'out'])->default('in')->after('media_filename');
            $table->string('recipient_number')->nullable()->after('direction');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['direction', 'recipient_number']);
        });
    }
};
