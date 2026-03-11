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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_group_id')->nullable()->constrained('whatsapp_groups')->nullOnDelete();
            $table->string('message_id')->unique()->nullable()->comment('whacenter message id');
            $table->string('sender_number');
            $table->string('sender_name')->nullable();
            $table->enum('message_type', ['text', 'image', 'document', 'video', 'audio', 'sticker', 'location', 'other'])->default('text');
            $table->longText('raw_body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_filename')->nullable();
            $table->boolean('is_processed')->default(false);
            $table->boolean('is_ad')->nullable();
            $table->decimal('ad_confidence', 3, 2)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->index(['is_processed', 'is_ad']);
            $table->index('sender_number');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
