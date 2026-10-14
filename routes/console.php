<?php

use App\Console\Commands\ExpirePendingBookings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The overlap lock expires after 10 minutes instead of the framework default
// of 1440 minutes (24 hours), so a run that dies mid-way cannot block expiry
// for a whole day while the command only runs every five minutes (T-014b).
Schedule::command(ExpirePendingBookings::class)
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
