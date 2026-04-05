<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Limit outbound WA DM replies to 2/minute (120/hour) so we stay safely
        // under the gateway's SEND_HOURLY_LIMIT=150. Jobs over the limit are
        // automatically re-released to queue with a ~60s delay — not failed.
        RateLimiter::for('wa-outbound', function () {
            return Limit::perMinute(2)->by('wa-outbound-global');
        });
    }
}
