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
    // Ensure APP_URL so the listener can build absolute URLs in testing
    config([
      'app.url' => config('app.url', 'http://mystorepanel.test')
    ]);

    $captured = null;
    $tenantId = 123; // any valid tenant id for the test

    // Capture the built Symfony Email right before it's sent
    Event::listen(
      MessageSending::class,
      function (MessageSending $event) use (&$captured): void {
        $captured = $event->message;
      }
    );

    // Send a simple mailable.
    // We inject X-Tenant-Id at the Symfony level so the listener emits the headers.
    Mail::to('dest@test.local')->send(
      new class($tenantId) extends Mailable {
        public function __construct(private readonly int $tenantId) {}

        /**
         * Build the message.
         */
        public function build(): Mailable
        {
          return $this
            ->subject('Test Deliverability')
            ->html('<p>Hello from test</p>')
            // Inject tenant header so the listener will emit List-Unsubscribe
            ->withSymfonyMessage(function (Email $email) {
              $email
                ->getHeaders()
                ->addTextHeader('X-Tenant-Id', (string)$this->tenantId);
            });
        }
      }
    );

    // We should have captured a Symfony Email instance
    $this->assertInstanceOf(Email::class, $captured);

    // Assert From fallback applied by provider
    $from = $captured->getFrom();
    $this->assertNotEmpty(
      $from,
      'From header should be present.'
    );
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
    $this->assertTrue(
      $headers->has('List-Unsubscribe'),
      'Missing List-Unsubscribe header'
    );

    $raw = $headers->get('List-Unsubscribe')->getBodyAsString();
    // Header may include multiple URIs: mailto, prefs page, and one-click.
    // Validate by containment.
    $this->assertStringContainsString(
      '/.well-known/list-unsubscribe',
      $raw,
      'Must include one-click endpoint'
    );
    $this->assertStringContainsString(
      '/unsubscribe',
      $raw,
      'Should include preferences page'
    );

    // If you configured a mailto in config,
    // assert it appears (handle both plain and prefixed forms)
    $mailtoCfg = (string)config('mystore.mail.list_unsubscribe', '');
    if ($mailtoCfg !== '') {
      $needle = str_starts_with($mailtoCfg, 'mailto:') ? $mailtoCfg : ('mailto:'.$mailtoCfg);
      $this->assertStringContainsString(
        $needle,
        $raw,
        'Should include mailto URI from config'
      );
    }

    $this->assertTrue(
      $headers->has('List-Unsubscribe-Post'),
      'Missing List-Unsubscribe-Post'
    );
    $this->assertSame(
      'List-Unsubscribe=One-Click',
      $headers->get('List-Unsubscribe-Post')->getBodyAsString(),
      'List-Unsubscribe-Post must be "List-Unsubscribe=One-Click"'
    );

    // Assert custom header
    $this->assertTrue($headers->has('X-System'));
    $this->assertSame(
      config('mystore.mail.headers.X-System'),
      $headers->get('X-System')->getBodyAsString()
    );

    // Assert Return-Path
    $this->assertTrue($headers->has('Return-Path'));
    $this->assertSame(
      '<'.env('MAIL_BOUNCE_ADDRESS').'>',
      $headers->get('Return-Path')->getBodyAsString()
    );
    $this->assertSame(
      '<'.config('mystore.mail.bounce').'>',
      $headers->get('Return-Path')->getBodyAsString()
    );
  }
}
