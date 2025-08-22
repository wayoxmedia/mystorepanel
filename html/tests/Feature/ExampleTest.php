<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Class ExampleTest.
 */
class ExampleTest extends TestCase
{
  /**
   * A basic test example.
   * @return void
   */
  public function testTheApplicationReturnsSuccessfulResponse(): void
  {
    $response = $this->get('/');

    $response->assertStatus(200);
  }
}
