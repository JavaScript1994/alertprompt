<?php

use App\Services\Deployment\DeploymentChecks;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('campaigns:dispatch-scheduled')->everyMinute();
// Cada hora y no solo de madrugada: si el scheduler estuvo caído, la siguiente
// corrida activa lo pendiente. Es idempotente.
Schedule::command('memberships:refresh')->hourlyAt(10)->withoutOverlapping();
Schedule::command('announcements:send-emails')->everyMinute()->withoutOverlapping();
Schedule::command('billing:daily')->dailyAt('00:30')->timezone('America/Lima');
// Latido: deploy:check avisa si el cron del scheduler dejó de correr.
Schedule::call(fn () => Cache::put(DeploymentChecks::HEARTBEAT_KEY, now()->toIso8601String(), now()->addDay()))
    ->everyMinute()
    ->name('scheduler-heartbeat');
