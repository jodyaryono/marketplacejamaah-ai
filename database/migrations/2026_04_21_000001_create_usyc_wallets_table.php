<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usyc_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique()->index();
            $table->string('arc_address', 42)->unique()->nullable()->comment('Arc blockchain wallet address (0x...)');
            $table->decimal('usyc_balance', 20, 8)->default(0)->comment('USYC balance (yield-bearing stablecoin)');
            $table->decimal('usyc_reserved', 20, 8)->default(0)->comment('Amount locked in pending transactions');
            $table->string('status')->default('active')->comment('active|suspended|kyc_pending');
            $table->boolean('is_verified')->default(false)->comment('KYC/identity verified');
            $table->json('metadata')->nullable()->comment('Additional wallet metadata');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usyc_wallets');
    }
};
