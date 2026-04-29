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

// Safety net: auto-approve sisa kontak `pending` setiap 10 menit.
// Sejak kebijakan no-manual-approval, status pending tidak dipakai lagi —
// ini cuma jaring kalau ada path lama / migrasi yang masih bikin pending.
Schedule::command('members:approve-pending')->everyTenMinutes()->withoutOverlapping();

// Safety net: gateway webhook `group_join` kadang missed (whatsapp-web.js unreliable).
// Sapu ulang anggota grup yang belum diregister/onboarding setiap 15 menit.
// --force skip confirm prompt; --max-send=10 + delay 6s = ~60s/run, aman di rate limiter.
Schedule::command('onboard:missing --force --max-send=10 --delay=6')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20)
    ->runInBackground();
