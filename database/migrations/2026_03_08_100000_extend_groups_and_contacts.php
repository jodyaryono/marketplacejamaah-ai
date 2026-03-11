<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop existing check constraint on onboarding_status so we can add new steps
        DB::statement('ALTER TABLE contacts DROP CONSTRAINT IF EXISTS contacts_onboarding_status_check');

        Schema::table('contacts', function (Blueprint $table) {
            $table
                ->text('sell_products')
                ->nullable()
                ->after('member_role')
                ->comment('Produk yang dijual (untuk role seller/both)');
            $table
                ->text('buy_products')
                ->nullable()
                ->after('sell_products')
                ->comment('Produk yang dicari (untuk role buyer/both)');
        });

        Schema::table('whatsapp_groups', function (Blueprint $table) {
            $table->unsignedInteger('admin_count')->default(0)->after('ad_count');
            $table
                ->json('participants_raw')
                ->nullable()
                ->after('admin_count')
                ->comment('Raw participant list from WA gateway API');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['sell_products', 'buy_products']);
        });

        Schema::table('whatsapp_groups', function (Blueprint $table) {
            $table->dropColumn(['admin_count', 'participants_raw']);
        });
    }
};
