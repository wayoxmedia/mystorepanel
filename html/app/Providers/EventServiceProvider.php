<?php

namespace App\Providers;

use App\Listeners\AddListUnsubscribeHeaders;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSending;

class EventServiceProvider extends ServiceProvider
{
  /**
   * The event listener mappings for the application.
   *
   * @var array<class-string, array<int, class-string>>
   */
  protected $listen = [
    // Add this line:
    MessageSending::class => [
      AddListUnsubscribeHeaders::class,
    ],
  ];
}
