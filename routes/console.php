<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Task Reminder Scheduler
|--------------------------------------------------------------------------
|
| Processes pending task reminders every minute.
|
| Local development:  php artisan schedule:work
| Production (cron):  * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

Schedule::command('reminders:process')->everyMinute();
Schedule::command('recurring-tasks:process')->everyMinute();
Schedule::command('processes:process')->everyMinute();
Schedule::command('processes:escalate')->everyMinute();
Schedule::command('processes:status')->hourly();
