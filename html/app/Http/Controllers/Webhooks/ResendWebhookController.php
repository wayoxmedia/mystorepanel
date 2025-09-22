<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Email\ResendEventHandler;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ResendWebhookController
 *
 * Purpose:
 * - Normalize Resend webhook payloads and delegate persistence to ResendEventHandler.
 * - Keep this controller thin: signature verification should be enforced by middleware
 *   (e.g., your VerifyServiceToken / Svix verification), not here.
 *
 * Assumptions:
 * - Route already points to this controller (POST /webhooks/resend or similar).
 * - A signature verification middleware already ran before reaching this point.
 * - We accept different shapes for convenience (Resend event "data" can vary by type).
 */
class ResendWebhookController extends Controller
{
  public function __construct(private readonly ResendEventHandler $handler) {}

  /**
   * Handle incoming Resend webhook requests.
   *
   * This method verifies the webhook signature and processes email events
   * to update subscriber records in the database.
   *
   * @param  Request  $request
   * @return Application|Response|JsonResponse|ResponseFactory
   */
  public function __invoke(Request $request): Application|Response|JsonResponse|ResponseFactory
  {
    // Raw body as array (Laravel parses JSON for us)
    $body = $request->json()->all();
    Log::info('Resend webhook hit', [
      'has_svix_id'        => $request->headers->has('svix-id'),
      'has_svix_timestamp' => $request->headers->has('svix-timestamp'),
      'has_svix_signature' => $request->headers->has('svix-signature'),
      'ua'                 => $request->userAgent(),
      'ip'                 => $request->ip(),
    ]);

    // 1) Basic fields
    $type = (string)Arr::get($body, 'type', '');

    // 2) Email resolution (try common locations)
    $email = (string)(
      Arr::get($body, 'email') ??
      Arr::get($body, 'data.to.0') ??
      Arr::get($body, 'to.0') ??
      ''
    );

    // 3) Tags (either top-level 'tags' or inside 'data.tags')
    $tagsRaw = Arr::get($body, 'tags');
    if (!is_array($tagsRaw)) {
      $tagsRaw = (array)Arr::get($body, 'data.tags', []);
    }

    // 4) Tenant resolution (prefer normalized key, then tags)
    $tenantId = Arr::get($body, 'tenant_id');
    if ($tenantId === null && isset($tagsRaw['tenant_id'])) {
      $tenantId = $tagsRaw['tenant_id'];
    }
    $tenantId = $tenantId !== null ? (int)$tenantId : null;

    // 5) Build normalized event for the handler
    $normalized = [
      'type' => $type,
      'email' => $email,
      'tenant_id' => $tenantId,
      'tags_raw' => !empty($tagsRaw) ? $tagsRaw : null,
      'data' => (array)Arr::get($body, 'data', []),
    ];

    try {
      $this->handler->handle($normalized);
    } catch (Throwable $e) {
      // Do not fail the webhook endpoint; log instead and acknowledge with 202.
      Log::warning('Resend webhook handler error', [
        'error' => $e->getMessage(),
        'type' => $type,
        'email' => $email,
        'tenant_id' => $tenantId,
      ]);

      return response()->json(['status' => 'ignored'], 202);
    }

    return response()->json(['status' => 'ok']);
  }
}
