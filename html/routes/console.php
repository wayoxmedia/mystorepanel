<?php

use App\Jobs\HealthQueuePingJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
  $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Schedule::call()->dailyAt('03:00');

Schedule::call(function () {
  Cache::put(
    'health:scheduler_beat',
    now()->timestamp,
    300
  ); // 5 min TTL
})->name('health:scheduler_beat')->everyMinute();

Schedule::job(new HealthQueuePingJob(), 'default')
  ->name('health:queue_beat')
  ->everyMinute();
