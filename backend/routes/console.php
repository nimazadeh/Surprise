<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work (shared hosting: single cron `schedule:run` every minute)
|--------------------------------------------------------------------------
|
| The database queue is drained in small bounded runs so no daemon is
| ever required. Cheap hosts with 15-minute cron granularity simply
| process jobs with up to 15 minutes of delay (UX copy must say so).
|
*/

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --max-jobs=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('drain-queue');
