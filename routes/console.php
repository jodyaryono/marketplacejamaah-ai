<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gateway:verify --quiet-ok')->everyThirtyMinutes();

// NOTE: hadith:send is already registered in bootstrap/app.php withSchedule().
// Do NOT re-register here — double registration causes the hadith to be sent twice.

Schedule::command('listings:expire --days=30')->dailyAt('02:00');

Schedule::command('agent-logs:prune --days=30')->dailyAt('03:00');

Schedule::command('messages:prune')->hourly();
