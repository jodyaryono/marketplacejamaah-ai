<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $indexes = [
            // listings.status — almost every query filters by status='active'
            'CREATE INDEX IF NOT EXISTS listings_status_index ON listings (status)',
            // listings.status + created_at — ordered active listing queries
            'CREATE INDEX IF NOT EXISTS listings_status_created_at_index ON listings (status, created_at DESC)',
            // listings.source_date — sorted by post date
            'CREATE INDEX IF NOT EXISTS listings_source_date_index ON listings (source_date DESC)',

            // messages.violation_detected — moderation queries
            'CREATE INDEX IF NOT EXISTS messages_violation_detected_index ON messages (violation_detected) WHERE violation_detected = true',
            // messages.message_category — dashboard deletion counts
            'CREATE INDEX IF NOT EXISTS messages_message_category_index ON messages (message_category)',
            // messages.is_processed — queue recovery scans
            'CREATE INDEX IF NOT EXISTS messages_is_processed_direction_index ON messages (is_processed, direction)',
            // messages.created_at — all "today" / date-range counts
            'CREATE INDEX IF NOT EXISTS messages_created_at_index ON messages (created_at DESC)',

            // contacts.is_registered — onboarding / seller queries
            'CREATE INDEX IF NOT EXISTS contacts_is_registered_index ON contacts (is_registered)',
            // contacts.onboarding_status — pending onboarding scans
            'CREATE INDEX IF NOT EXISTS contacts_onboarding_status_index ON contacts (onboarding_status)',
            // contacts.warning_count + total_violations — violators dashboard
            'CREATE INDEX IF NOT EXISTS contacts_violations_index ON contacts (total_violations DESC, warning_count DESC)',
        ];

        foreach ($indexes as $sql) {
            \Illuminate\Support\Facades\DB::statement($sql);
        }
    }

    public function down(): void
    {
        $drops = [
            'DROP INDEX IF EXISTS listings_status_index',
            'DROP INDEX IF EXISTS listings_status_created_at_index',
            'DROP INDEX IF EXISTS listings_source_date_index',
            'DROP INDEX IF EXISTS messages_violation_detected_index',
            'DROP INDEX IF EXISTS messages_message_category_index',
            'DROP INDEX IF EXISTS messages_is_processed_direction_index',
            'DROP INDEX IF EXISTS messages_created_at_index',
            'DROP INDEX IF EXISTS contacts_is_registered_index',
            'DROP INDEX IF EXISTS contacts_onboarding_status_index',
            'DROP INDEX IF EXISTS contacts_violations_index',
        ];

        foreach ($drops as $sql) {
            \Illuminate\Support\Facades\DB::statement($sql);
        }
    }
};
