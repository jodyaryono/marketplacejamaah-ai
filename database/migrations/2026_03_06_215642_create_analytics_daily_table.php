<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('whatsapp_group_id')->nullable()->constrained('whatsapp_groups')->nullOnDelete();
            $table->unsignedInteger('total_messages')->default(0);
            $table->unsignedInteger('total_ads')->default(0);
            $table->unsignedInteger('total_contacts')->default(0);
            $table->unsignedInteger('total_listings')->default(0);
            $table->timestamps();
            $table->unique(['date', 'whatsapp_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');
    }
};
