<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nhắc lịch chăm sóc đến hạn mỗi phút (cần chạy `php artisan schedule:work` hoặc cron).
Schedule::command('reminders:dispatch')->everyMinute()->withoutOverlapping();
