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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('whatsapp_group_id')->nullable()->constrained('whatsapp_groups')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('price_min', 15, 2)->nullable();
            $table->decimal('price_max', 15, 2)->nullable();
            $table->string('price_label')->nullable()->comment('e.g. Rp500rb/pcs');
            $table->string('contact_number')->nullable();
            $table->string('contact_name')->nullable();
            $table->json('media_urls')->nullable();
            $table->string('location')->nullable();
            $table->enum('condition', ['new', 'used', 'unknown'])->default('unknown');
            $table->enum('status', ['active', 'sold', 'expired', 'pending'])->default('pending');
            $table->timestamp('source_date')->nullable();
            $table->timestamps();
            $table->index(['status', 'category_id']);
            $table->index('contact_number');
            $table->index('source_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
