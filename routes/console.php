<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// Respaldo automático: cada hora revisa si ya hay uno de hoy (y si ya pasó la hora configurada).
Schedule::command('respaldo:crear --si-falta')
    ->hourly()
    ->withoutOverlapping(60);
