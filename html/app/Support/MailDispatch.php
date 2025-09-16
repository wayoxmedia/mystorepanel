<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Helper to deliver mail according to config.
 *
 * Config options:
 * - mystore.mail.dispatch: 'queue' | 'send' | 'auto' (default: 'queue')
 * - mystore.mail.queue: queue name when queueing (default: 'mail')
 *
 * In 'auto' we queue in non-testing, and send in testing.
 */
final class MailDispatch
{
  /**
   * Deliver a mailable according to config:
   * - mystore.mail.dispatch: 'queue' | 'send' | 'auto'
   * - mystore.mail.queue: queue name when queueing
   *
   * In 'auto' we queue in non-testing, and send in testing.
   */
  public static function deliver(Mailable $mailable, string|array $to): void
  {
    $strategy  = config('mystore.mail.dispatch', 'queue');
    $queueName = (string) config('mystore.mail.queue', 'mail');

    // Resolve 'auto' into a concrete strategy first
    if ($strategy === 'auto') {
      $strategy = app()->environment('testing') ? 'send' : 'queue';
    }

    $mailer = Mail::to($to);

    if ($strategy === 'send') {
      $mailer->send($mailable);
      return;
    }

    if ($strategy === 'queue') {
      // Allow mailable to carry queue name from config
      if (method_exists($mailable, 'onQueue') && $queueName !== '') {
        $mailable->onQueue($queueName);
      }
      $mailer->queue($mailable);
    }
  }
}
