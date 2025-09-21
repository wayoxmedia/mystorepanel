<?php

namespace Tests\Feature\Mail;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class DeliverabilityHeadersNegativeTest extends TestCase
{
  public function testListUnsubscribeIsNotEmittedWithoutTenantId(): void
  {
    // Ensure APP_URL exists so URL-building code has a base (even if not used here)
    config(['app.url' => config(
      'app.url',
      'http://mystorepanel.test'
    )]);

    $captured = null;

    // Capture the Symfony Email right before it's sent
    Event::listen(
      MessageSending::class,
      function (MessageSending $event) use (&$captured): void {
        $captured = $event->message;
      });

    // Send a simple mailable WITHOUT injecting X-Tenant-Id
    Mail::to('dest@test.local')->send(new class extends Mailable {
      public function build(): Mailable
      {
        return $this
          ->subject('Deliverability Negative Test (no tenant)')
          ->html('<p>Hello from negative test</p>');
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

    // NEGATIVE: Without X-Tenant-Id,
    // List-Unsubscribe MUST NOT be emitted
    $headers = $captured->getHeaders();
    $this->assertFalse(
      $headers->has('List-Unsubscribe'),
      'List-Unsubscribe must NOT be present without X-Tenant-Id'
    );

    // We do NOT assert List-Unsubscribe-Post here
    // (contract only forbids List-Unsubscribe)
    // But we keep other global headers to ensure nothing else breaks:

    // Custom header
    $this->assertTrue($headers->has('X-System'));
    $this->assertSame(
      config('mystore.mail.headers.X-System'),
      $headers->get('X-System')->getBodyAsString()
    );

    // Return-Path (added for logs/testing via Path header)
    $this->assertTrue(
      $headers->has('Return-Path'),
      'Return-Path should be present for visibility in tests/logs'
    );
    $this->assertSame(
      '<' . config('mystore.mail.bounce') . '>',
      $headers->get('Return-Path')->getBodyAsString()
    );
  }
}
