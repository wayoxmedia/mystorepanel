<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class HealthQueuePingJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct()
  {
    $this->onQueue('default');
  }

  /**
   * Handle the job.
   * @return void
   */
  public function handle(): void
  {
    Cache::put(
      'health:queue_beat',
      now()->timestamp, 300
    ); // 5 min TTL
  }
}
