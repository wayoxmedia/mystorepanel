<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Svix\Webhook;
use Throwable;

/**
 * Controller to handle Resend webhooks for email events.
 *
 * This controller verifies the webhook signature using Svix and processes
 * email events such as bounces and complaints to update subscriber status.
 */
class ResendWebhookController extends Controller
{
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
    // 1) Leer el RAW body (requisito para verificar firma)
    $payload = $request->getContent();
    Log::info('Resend webhook hit', [
      'has_svix_id'        => $request->headers->has('svix-id'),
      'has_svix_timestamp' => $request->headers->has('svix-timestamp'),
      'has_svix_signature' => $request->headers->has('svix-signature'),
      'ua'                 => $request->userAgent(),
      'ip'                 => $request->ip(),
    ]);


    // 2) Headers posibles: svix-* (principal) o webhook-* (algunas integraciones)
    $svixId        = $request->header('svix-id')
      ?? $request->header('webhook-id');
    $svixTimestamp = $request->header('svix-timestamp')
      ?? $request->header('webhook-timestamp');
    $svixSignature = $request->header('svix-signature')
      ?? $request->header('webhook-signature');
    $headers = [
      'svix-id'        => $svixId,
      'svix-timestamp' => $svixTimestamp,
      'svix-signature' => $svixSignature,
    ];

    // 3) Verificar firma con Svix (usar el whsec_* tal cual)
    $secret = (string) config(
      'services.resend.webhook_secret',
      env('RESEND_WEBHOOK_SECRET')
    );
    if ($secret === '') {
      Log::warning('Resend webhook: missing secret');
      return response('Misconfigured', 500);
    }

    try {
      $wh = new Webhook($secret);
      $json = $wh->verify($payload, $headers); // throws si invalido
      $verified = $wh->verify($payload, $headers);
      // $event = json_decode($json, true) ?? [];
      $event = is_array($verified)
        ? $verified
        : json_decode(
          $verified,
          true,
          512,
          JSON_THROW_ON_ERROR
        );
    } catch (Throwable $e) {
      Log::warning(
        'Resend webhook: signature verification failed',
        [
          'error' => $e->getMessage()
        ]
      );
      return response('Invalid signature', 400);
    }

    // 4) Procesar eventos
    $type = (string) ($event['type'] ?? '');
    $data = (array)   ($event['data'] ?? []);

    // to: puede venir como string, como array de strings o de objetos
    $toRaw = $data['to'] ?? ($data['email'] ?? null);
    if (is_array($toRaw)) {
      $first = $toRaw[0] ?? null;
      if (is_string($first)) {
        $email = strtolower($first);
      } elseif (is_array($first) && isset($first['email'])) {
        $email = strtolower((string) $first['email']);
      } else {
        $email = '';
      }
    } else {
      $email = strtolower((string) $toRaw);
    }

    $tags = $data['tags'] ?? [];
    $tenantId = null;
    if (is_array($tags)) {
      $isList = array_is_list($tags);
      if ($isList) {
        foreach ($tags as $tag) {
          if (($tag['name'] ?? '') === 'tenant_id') {
            $tenantId = (int) ($tag['value'] ?? 0);
            break;
          }
        }
      } else {
        if (isset($tags['tenant_id'])) {
          $tenantId = (int) $tags['tenant_id'];
        }
      }
    }

    // tenant_id desde tags si viene (recomendado cuando envíes con Resend)
    Log::info('Resend webhook received', [
      'type' => $type,
      'email' => $email,
      'tenant_id'  => $tenantId,
      'tags_raw'   => $event['data']['tags'] ?? null,
    ]);

    $now  = now();
    $meta = [
      'event'      => $type,
      'receivedAt' => $now->toIso8601String(),
      'raw'        => $event, // guarda payload para auditoría
    ];

    // Base query (canal email)
    $q = DB::table('subscribers')
      ->where('address_type', 'e')
      ->whereRaw('LOWER(address) = ?', [$email]);

    if ($tenantId) {
      $q->where('tenant_id', $tenantId);
    }

    switch ($type) {
      case 'email.complained':
        $q->update([
          'complained_at'       => $now,
          'unsubscribed_at'     => $now,
          'unsubscribe_source'  => 'complaint',
          'unsubscribe_meta'    => json_encode(
            $meta,
            JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
          ),
          'active'              => 0,
          'updated_at'          => $now,
        ]);
        break;

      case 'email.bounced':
        $bounceType = strtolower((string) ($data['bounce']['type'] ?? ''));
        $isPermanent = ($bounceType === 'permanent');

        // Incremento atómico de rebotes
        $q->update([
          'bounce_count'        => DB::raw('bounce_count + 1'),
          'unsubscribe_meta'    => json_encode(
            $meta,
            JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
          ),
          'updated_at'          => $now,
        ]);

        if ($isPermanent) {
          $q->update([
            'unsubscribed_at'    => $now,
            'unsubscribe_source' => 'bounce',
            'active'             => 0,
          ]);
        }
        break;

      default:
        // Ignoramos otros eventos por ahora (delivered/opened/clicked/failed)
        break;
    }

    return response()->json(['ok' => true]);
  }
}
