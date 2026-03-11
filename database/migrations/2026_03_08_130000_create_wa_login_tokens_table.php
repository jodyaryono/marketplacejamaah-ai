<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wa_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->index();
            // Short-lived OTP (6 digits, expires in 10 minutes)
            $table->string('otp', 6)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->integer('otp_attempts')->default(0);
            // Long-lived remember token (6 months)
            $table->string('remember_token', 80)->unique()->nullable();
            $table->timestamp('remember_expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_login_tokens');
    }
};
