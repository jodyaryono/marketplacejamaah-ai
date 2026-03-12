<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gateway:verify --quiet-ok')->everyThirtyMinutes();

Schedule::command('hadith:send')->everyMinute();

Schedule::command('listings:expire --days=30')->dailyAt('02:00');

Schedule::command('agent-logs:prune --days=30')->dailyAt('03:00');
