<?php

use App\Console\Commands\CloseExpiredActivities;
use App\Console\Commands\ConcludeExpiredEvents;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ConcludeExpiredEvents::class)->hourly();
Schedule::command(CloseExpiredActivities::class)->dailyAt('01:00');
