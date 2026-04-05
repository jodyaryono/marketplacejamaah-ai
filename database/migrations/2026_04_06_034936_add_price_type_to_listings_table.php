<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // fix = harga tetap, nego = bisa nego, lelang = harga lelang/starting bid
            $table->string('price_type', 10)->default('fix')->after('price_label');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('price_type');
        });
    }
};
