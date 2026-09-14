<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('meetings:prune-audio')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('investments:sync --universe=ecosystem --queue')->weeklyOn(1, '02:00')->withoutOverlapping();
