<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Mailable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Symfony\Component\Mime\Email;

/**
 * Verifies global deliverability headers injected via AppServiceProvider:
 * - From fallback (if not set on the Mailable)
 * - List-Unsubscribe and List-Unsubscribe-Post
 * - Return-Path via envelope sender (bounce)
 * - Custom headers (X-System)
 */
final class DeliverabilityHeadersTest extends BaseTestCase
{
  use RefreshDatabase;

  /**
   * Test that global headers are injected via the MessageSending event.
   * @return void
   */
  public function testGlobalHeadersAreInjectedViaMessageSendingEvent(): void
  {
    $captured = null;

    // Capture the built Symfony Email right before it's sent
    Event::listen(MessageSending::class, function (MessageSending $event) use (&$captured): void {
      $captured = $event->message;
    });

    // Send a simple mailable WITHOUT ->from() so provider fills it
    Mail::to('dest@test.local')->send(new class extends Mailable {
      /**
       * Mailable build method.
       * @return Mailable
       */
      public function build(): Mailable
      {
        return $this
          ->subject('Test Deliverability')
          ->html('<p>Hello from test</p>');
      }
    });

    // We should have captured a Symfony Email instance
    $this->assertInstanceOf(Email::class, $captured);

    // Assert From fallback applied by provider
    $from = $captured->getFrom();
    $this->assertNotEmpty($from, 'From header should be present.');
    $this->assertSame(
      env('MAIL_FROM_ADDRESS'),
      $from[0]->getAddress()
    );
    $this->assertSame(
      config('mystore.mail.from.address'),
      $from[0]->getAddress()
    );

    $this->assertSame(
      env('MAIL_FROM_NAME'),
      $from[0]->getName()
    );
    $this->assertSame(
      config('mystore.mail.from.name'),
      $from[0]->getName()
    );

    // Assert List-Unsubscribe and One-Click headers
    $headers = $captured->getHeaders();
    $this->assertTrue($headers->has('List-Unsubscribe'));
    /* TODO .env entry was removed, need to figure out how to test this properly
    $this->assertSame(
      env('MAIL_LIST_UNSUBSCRIBE'),
      $headers->get('List-Unsubscribe')->getBodyAsString()
    );
    */
    $this->assertSame(
      config('mystore.mail.list_unsubscribe'),
      $headers->get('List-Unsubscribe')->getBodyAsString()
    );

    $this->assertTrue($headers->has('List-Unsubscribe-Post'));
    $this->assertSame(
      'List-Unsubscribe=One-Click',
      $headers->get('List-Unsubscribe-Post')->getBodyAsString()
    );

    // Assert custom header
    $this->assertTrue($headers->has('X-System'));
    $this->assertSame(
      config('mystore.mail.headers.X-System'),
      $headers->get('X-System')->getBodyAsString()
    );

    $this->assertTrue($headers->has('Return-Path'));
    $this->assertSame(
      '<' . env('MAIL_BOUNCE_ADDRESS') . '>',
      $headers->get('Return-Path')->getBodyAsString()
    );
    $this->assertSame(
      '<' . config('mystore.mail.bounce') . '>',
      $headers->get('Return-Path')->getBodyAsString()
    );
  }
}
