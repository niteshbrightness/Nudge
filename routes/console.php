<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('nudge:send-notifications')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping(); // This should be not push on live
