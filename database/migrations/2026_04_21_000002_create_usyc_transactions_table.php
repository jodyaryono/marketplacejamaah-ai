<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usyc_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tx_hash')->unique()->nullable()->comment('Arc blockchain tx hash');
            $table->string('tx_type')->default('payment')->comment('payment|escrow|release|refund|topup|withdraw');

            // Parties
            $table->string('sender_phone')->nullable()->index();
            $table->string('receiver_phone')->nullable()->index();
            $table->string('sender_arc_address', 42)->nullable();
            $table->string('receiver_arc_address', 42)->nullable();

            // Amount
            $table->decimal('amount_usyc', 20, 8)->comment('Amount in USYC');
            $table->decimal('amount_usd', 20, 8)->nullable()->comment('USD equivalent at time of tx');
            $table->decimal('fee_usyc', 20, 8)->default(0)->comment('Platform/network fee in USYC');
            $table->decimal('yield_earned', 20, 8)->default(0)->comment('Yield accrued while in escrow');

            // Status
            $table->string('status')->default('pending')
                ->comment('pending|processing|confirmed|failed|refunded|expired');
            $table->integer('confirmations')->default(0);
            $table->integer('required_confirmations')->default(1);

            // Context
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->string('whatsapp_message_id')->nullable()->comment('WA message that triggered this tx');
            $table->string('whatsapp_group_id')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            // Arc blockchain data
            $table->string('arc_block_number')->nullable();
            $table->timestamp('arc_confirmed_at')->nullable();
            $table->json('arc_raw_receipt')->nullable()->comment('Raw blockchain receipt');

            // Escrow
            $table->timestamp('escrow_release_at')->nullable()->comment('Auto-release escrow after this time');
            $table->string('escrow_status')->nullable()->comment('held|released|disputed|refunded');

            $table->timestamps();
        });

        // Add wallet references to contacts
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('arc_wallet_address', 42)->nullable()->after('longitude');
            $table->decimal('usyc_balance_cache', 20, 8)->default(0)->after('arc_wallet_address');
        });

        // Add payment support to listings
        Schema::table('listings', function (Blueprint $table) {
            $table->decimal('price_usyc', 20, 8)->nullable()->after('price_label')
                ->comment('Price in USYC (nanopayment supported)');
            $table->boolean('accepts_usyc')->default(false)->after('price_usyc');
            $table->string('payment_status')->nullable()->after('accepts_usyc')
                ->comment('unpaid|payment_pending|paid|escrow_held');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['price_usyc', 'accepts_usyc', 'payment_status']);
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['arc_wallet_address', 'usyc_balance_cache']);
        });
        Schema::dropIfExists('usyc_transactions');
    }
};
