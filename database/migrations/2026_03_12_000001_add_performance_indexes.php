<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Use raw SQL with IF NOT EXISTS to handle already-existing indexes gracefully
        $indexes = [
            'CREATE INDEX IF NOT EXISTS listings_contact_id_index ON listings (contact_id)',
            'CREATE INDEX IF NOT EXISTS listings_whatsapp_group_id_index ON listings (whatsapp_group_id)',
            'CREATE INDEX IF NOT EXISTS messages_whatsapp_group_id_index ON messages (whatsapp_group_id)',
            'CREATE INDEX IF NOT EXISTS messages_sender_number_index ON messages (sender_number)',
            'CREATE INDEX IF NOT EXISTS messages_is_ad_whatsapp_group_id_index ON messages (is_ad, whatsapp_group_id)',
        ];

        foreach ($indexes as $sql) {
            \Illuminate\Support\Facades\DB::statement($sql);
        }
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['contact_id']);
            $table->dropIndex(['whatsapp_group_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_group_id']);
            $table->dropIndex(['sender_number']);
            $table->dropIndex(['is_ad', 'whatsapp_group_id']);
        });
    }
};
