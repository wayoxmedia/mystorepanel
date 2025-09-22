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
    MessageSending::class => [
      AddListUnsubscribeHeaders::class,
    ],
  ];

  public function boot(): void
  {
    // nothing here — mapping via $listen is enough
  }

  public function shouldDiscoverEvents(): bool
  {
    return false; // set to true only if you prefer discovery and clear $listen
  }
}
