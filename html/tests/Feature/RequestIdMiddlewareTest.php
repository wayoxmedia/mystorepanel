<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * RequestIdMiddlewareTest
 *
 * Verifies that the RequestId middleware:
 *  - Generates a UUID v4 when the client does not send X-Request-Id.
 *  - Preserves a safe client-provided X-Request-Id.
 *  - Replaces unsafe/invalid values with a UUID v4.
 *
 * Assumes:
 *  - bootstrap/app.php prepends App\Http\Middleware\RequestId to the 'api' group.
 */
class RequestIdMiddlewareTest extends TestCase
{
  protected string $uuidV4 =
    '/^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-4[0-9a-fA-F]{3}\-[89abAB][0-9a-fA-F]{3}\-[0-9a-fA-F]{12}$/';
  protected function setUp(): void
  {
    parent::setUp();

    // Define a simple API route so the 'api' group (and RequestId middleware) runs.
    Route::middleware('api')
      ->get(
        '/_test/request-id',
        fn () => response()->json(['ok' => true])
      );
  }

  /**
   * Test that a UUID v4 is generated when X-Request-Id is missing.
   * @return void
   */
  public function testGeneratesUuidWhenHeaderIsMissing(): void
  {
    $res = $this->withHeaders(['Accept' => 'application/json'])
      ->getJson('/_test/request-id');

    $res->assertOk()->assertJson(['ok' => true]);

    $id = $res->headers->get('X-Request-Id');
    $this->assertNotEmpty($id, 'X-Request-Id should be present');

    $this->assertMatchesRegularExpression(
      $this->uuidV4,
      $id,
      'X-Request-Id should be a UUID v4 when missing'
    );
  }

  /**
   * Test that a safe client-provided X-Request-Id is preserved.
   * @return void
   */
  public function testPreservesSafeClientProvidedRequest_id(): void
  {
    // Safe value matches middleware pattern (8–128 chars, A-Z/a-z/0-9/._-)
    $clientId = 'req_ABC-123.test-xyz';

    $res = $this->withHeaders([
      'Accept'        => 'application/json',
      'X-Request-Id'  => $clientId,
    ])
      ->getJson('/_test/request-id');

    $res->assertOk()->assertJson(['ok' => true]);

    $returned = $res->headers->get('X-Request-Id');
    $this->assertSame(
      $clientId,
      $returned,
      'Middleware must preserve a safe X-Request-Id from client'
    );
  }

  /**
   * Test that an unsafe client-provided X-Request-Id is replaced with a UUID v4.
   * @return void
   */
  public function testReplacesUnsafeClientRequestIdWithUuid(): void
  {
    // Too short and contains an illegal space -> should be replaced by a UUID v4
    $unsafeClientId = 'bad id';

    $res = $this->withHeaders([
      'Accept'        => 'application/json',
      'X-Request-Id'  => $unsafeClientId,
    ])
      ->getJson('/_test/request-id');

    $res->assertOk()->assertJson(['ok' => true]);

    $returned = $res->headers->get('X-Request-Id');
    $this->assertNotSame(
      $unsafeClientId,
      $returned,
      'Unsafe X-Request-Id must be replaced'
    );

    $this->assertMatchesRegularExpression(
      $this->uuidV4,
      $returned,
      'Replacement should be a UUID v4'
    );
  }
}
