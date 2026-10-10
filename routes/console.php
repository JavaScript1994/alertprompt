<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('campaigns:dispatch-scheduled')->everyMinute();
// Cada hora y no solo de madrugada: si el scheduler estuvo caído, la siguiente
// corrida activa lo pendiente. Es idempotente.
Schedule::command('memberships:refresh')->hourlyAt(10)->withoutOverlapping();
Schedule::command('billing:daily')->dailyAt('00:30')->timezone('America/Lima');
