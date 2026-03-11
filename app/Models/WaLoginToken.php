<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WaLoginToken extends Model
{
    protected $fillable = [
        'phone_number',
        'otp',
        'otp_expires_at',
        'otp_attempts',
        'remember_token',
        'remember_expires_at',
        'last_used_at',
        'ip_address',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'remember_expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function isOtpValid(string $otp): bool
    {
        return $this->otp !== null &&
            $this->otp === $otp &&
            $this->otp_expires_at !== null &&
            $this->otp_expires_at->isFuture() &&
            $this->otp_attempts < 5;
    }

    public function isRememberTokenValid(string $token): bool
    {
        return $this->remember_token !== null &&
            hash_equals($this->remember_token, $token) &&
            $this->remember_expires_at !== null &&
            $this->remember_expires_at->isFuture();
    }

    /**
     * Generate a new OTP and persist, returning the plain OTP value.
     */
    public static function generateOtp(string $phoneNumber, string $ip): array
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $record = self::updateOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'otp' => $otp,
                'otp_expires_at' => now()->addMinutes(10),
                'otp_attempts' => 0,
                'ip_address' => $ip,
            ]
        );

        return [$record, $otp];
    }

    /**
     * After successful OTP verification, create a 6-month remember token.
     */
    public function activateRememberToken(): string
    {
        $token = Str::random(60);
        $this->update([
            'otp' => null,
            'otp_expires_at' => null,
            'remember_token' => $token,
            'remember_expires_at' => now()->addMonths(6),
            'last_used_at' => now(),
        ]);
        return $token;
    }
}
