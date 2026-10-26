<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Enrolment v2 processor.
 *
 * Every minute, matching the WordPress 'ams2_one_min' cron schedule.
 *
 * withoutOverlapping(10) expires the scheduler's own mutex after 10 minutes, so
 * a run killed mid-flight (deploy, OOM) cannot wedge the schedule permanently.
 * The processor also takes its own cache lock, so a run started any other way
 * still cannot double up.
 */
Schedule::command('ams2:process')
    ->everyMinute()
    ->withoutOverlapping(10);
