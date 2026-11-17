<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('meetings:prune-audio')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('investments:sync --universe=ecosystem --queue')->weeklyOn(1, '02:00')->withoutOverlapping();
Schedule::command('open-data:refresh --queue')->everySixHours()->withoutOverlapping();
Schedule::command('siid:sincronizar-listado --apply --origin=programado')
    ->dailyAt((string) config('siid.sheets.sync_time', '02:30'))
    ->withoutOverlapping();
Schedule::command('inteligencia:sincronizar')->hourly()->withoutOverlapping(720);
Schedule::command('geospatial:prune-public')->hourly()->withoutOverlapping();
Schedule::command('micrositio:sincronizar-mapa-inicio')
    ->dailyAt('04:10')
    ->withoutOverlapping(120);
