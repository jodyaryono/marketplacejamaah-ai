<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            // The message being reacted to (nullable in case we don't have it stored)
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            // The WA message_id of the target message (always available from webhook)
            $table->string('target_message_id')->nullable()->index();
            // Who reacted
            $table->string('sender_number');
            $table->string('sender_name')->nullable();
            // The emoji reaction (e.g. "❤️", "👍", "😂"), null = reaction removed
            $table->string('emoji')->nullable();
            // Group context (null for DM reactions)
            $table->foreignId('whatsapp_group_id')->nullable()->constrained('whatsapp_groups')->nullOnDelete();
            // Raw webhook payload
            $table->json('raw_payload')->nullable();
            $table->timestamp('reacted_at')->nullable();
            $table->timestamps();
            $table->index(['sender_number', 'target_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
