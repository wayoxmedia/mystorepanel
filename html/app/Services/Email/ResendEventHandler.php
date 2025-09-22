<?php

namespace App\Services\Email;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ResendEventHandler
 *
 * Normalizes and persists Resend webhook events into the `subscribers` table.
 *
 * Expected normalized payload (from your webhook controller):
 * [
 *   'type'       => 'email.bounced'|'email.complained'|'email.delivered'|...,
 *   'email'      => 'user@example.com',
 *   'tenant_id'  => 123|null,
 *   'tags_raw'   => [ 'tenant_id' => '123', ... ]|null,
 *   'data'       => [ ... original resend "data" object ... ]|null
 * ]
 *
 * Table schema recap (as provided):
 * - id (bigint, pk)
 * - tenant_id (bigint, unsigned, nullable)
 * - address (varchar, not null)
 * - unsubscribed_at (timestamp, nullable)
 * - unsubscribe_source (varchar(32), nullable)
 * - unsubscribe_meta (json, nullable)
 * - bounce_count (smallint unsigned, default 0)
 * - complained_at (timestamp, nullable)
 * - address_type (enum('p','e'))  // 'e' for email, 'p' for phone
 * - user_ip (varchar(45), nullable)
 * - active (tinyint(1) not null default 1)
 * - created_at/updated_at/geo_location...
 */
class ResendEventHandler
{
  /**
   * Handle a single event payload.
   *
   * @param  array<string, mixed>  $event
   * @return void
   */
  public function handle(array $event): void
  {
    $type = (string)($event['type'] ?? '');
    $emailRaw = (string)($event['email'] ?? Arr::get(
      $event,
      'data.to.0',
      '')
    );
    $email = strtolower(trim($emailRaw));
    $tenantId = $this->resolveTenantId($event);

    if ($email === '') {
      // Nothing to do if we can't identify the recipient.
      return;
    }

    // Route by type
    switch ($type) {
      case 'email.bounced':
        $this->handleBounce($tenantId, $email, $event);
        break;

      case 'email.complained':
        $this->handleComplaint($tenantId, $email, $event);
        break;

      // We may extend these later for analytics:
      case 'email.delivered':
      case 'email.opened':
      case 'email.clicked':
      case 'email.delivery_delayed':
      case 'email.sent':
      default:
        // No-op for now
        break;
    }
  }

  /**
   * Extract tenant_id from payload (prefer normalized key, fallback to tags).
   * If not found or invalid, returns null.
   * @param  array<string, mixed>  $event
   * @return int|null
   */
  protected function resolveTenantId(array $event): ?int
  {
    $tid = $event['tenant_id'] ?? null;

    if ($tid === null && isset($event['tags_raw']['tenant_id'])) {
      $tid = $event['tags_raw']['tenant_id'];
    }

    if ($tid === null && isset($event['data']['tags']['tenant_id'])) {
      $tid = $event['data']['tags']['tenant_id'];
    }

    if ($tid === null) {
      return null;
    }

    $tid = (int)$tid;
    return $tid > 0 ? $tid : null;
  }

  /**
   * On bounce:
   * - upsert subscriber row (address_type='e')
   * - increment bounce_count
   * - if bounce is Permanent (hard) => deactivate and unsubscribe immediately
   *   otherwise (transient) just increment count; you can later add thresholds.
   * - record meta
   * @param  int|null  $tenantId
   * @param  string  $email
   * @param  array<string, mixed>  $event
   * @return void
   */
  protected function handleBounce(?int $tenantId, string $email, array $event): void
  {
    $now = Carbon::now();
    $data = (array)($event['data'] ?? []);
    $bounceInfo = (array)($data['bounce'] ?? []);
    // 'Permanent' | 'Transient' | ...
    $bounceType = (string)($bounceInfo['type'] ?? 'Unknown');
    // 'Suppressed' | 'General' | ...
    $bounceSub = (string)($bounceInfo['subType'] ?? 'Unknown');
    $reason = (string)($bounceInfo['message'] ?? '');

    // Create or fetch current row
    $row = $this->upsertSkeleton($tenantId, $email);

    // Increment bounce_count
    $newBounceCount = ((int)$row['bounce_count']) + 1;

    // Decide suppression policy:
    // If Permanent (hard bounce) => immediate suppression.
    // If Transient => only increment (you can change policy later).
    $shouldSuppress = strcasecmp($bounceType, 'Permanent') === 0;

    // Merge meta
    $meta = $this->mergeUnsubMeta(
      $row['unsubscribe_meta'] ?? null,
      [
        'event' => 'email.bounced',
        'bounce' => ['type' => $bounceType, 'subType' => $bounceSub, 'message' => $reason],
        'tags_raw' => $event['tags_raw'] ?? null,
        'email_id' => Arr::get($data, 'email_id'),
        'broadcast_id' => Arr::get($data, 'broadcast_id'),
      ]
    );

    $update = [
      'bounce_count' => $newBounceCount,
      'updated_at' => $now,
    ];

    if ($shouldSuppress) {
      $update += [
        'active' => 0,
        'unsubscribed_at' => $now,
        'unsubscribe_source' => 'bounce',
        'unsubscribe_meta' => $this->j($meta),
      ];
    } else {
      // Keep meta, but don't force unsubscribe for soft/transient bounces.
      $update += [
        'unsubscribe_meta' => $this->j($meta),
      ];
    }

    DB::table('subscribers')
      ->where('id', $row['id'])
      ->update($update);
  }

  /**
   * On complaint:
   * - upsert subscriber row (address_type='e')
   * - set complained_at, deactivate and unsubscribe immediately
   * - record meta
   * Note: complaints are always final, no thresholds.
   * @param  int|null  $tenantId
   * @param  string  $email
   * @param  array<string, mixed>  $event
   * @return void
   */
  protected function handleComplaint(?int $tenantId, string $email, array $event): void
  {
    $now = Carbon::now();
    $data = (array)($event['data'] ?? []);

    $row = $this->upsertSkeleton($tenantId, $email);

    $meta = $this->mergeUnsubMeta(
      $row['unsubscribe_meta'] ?? null,
      [
        'event' => 'email.complained',
        'tags_raw' => $event['tags_raw'] ?? null,
        'email_id' => Arr::get($data, 'email_id'),
        'broadcast_id' => Arr::get($data, 'broadcast_id'),
      ]
    );

    DB::table('subscribers')
      ->where('id', $row['id'])
      ->update([
        'active' => 0,
        'complained_at' => $now,
        'unsubscribed_at' => $now,
        'unsubscribe_source' => 'complaint',
        'unsubscribe_meta' => $this->j($meta),
        'updated_at' => $now,
      ]);
  }

  /**
   * Ensure a minimal row exists for (tenant_id, address, 'e').
   *
   * Returns the current row as associative array.
   * If already exists, does not modify it.
   * If not, creates a new active row with bounce_count=0.
   * This is used to ensure we have a row to update on events.
   * Note: does not handle address_type='p' (phone) as Resend only deals with email.
   * @param  int|null  $tenantId
   * @param  string  $email
   * @return array<string, mixed>  Current or newly created row
   */
  protected function upsertSkeleton(?int $tenantId, string $email): array
  {
    $now = Carbon::now();

    // Try to find existing record (exact match by tenant and address)
    $existing = DB::table('subscribers')
      ->where('address', $email)
      ->where('address_type', 'e')
      ->when($tenantId !== null, fn($q) => $q->where('tenant_id', $tenantId))
      ->when($tenantId === null, fn($q) => $q->whereNull('tenant_id'))
      ->first();

    if ($existing) {
      return (array)$existing;
    }

    // Insert skeleton row
    $id = DB::table('subscribers')->insertGetId([
      'tenant_id' => $tenantId,
      'address' => $email,
      'address_type' => 'e',
      'active' => 1,
      'bounce_count' => 0,
      'created_at' => $now,
      'updated_at' => $now,
    ]);

    return (array)DB::table('subscribers')
      ->where('id', $id)
      ->first();
  }

  /**
   * Merge unsubscribe_meta JSON preserving historical entries (simple append list).
   *
   * @param  mixed  $current  Existing JSON string/object/array|null
   * @param  array  $addition  Info to append
   * @return array
   */
  protected function mergeUnsubMeta(mixed $current, array $addition): array
  {
    $asArray = [];

    if (is_string($current)) {
      $decoded = json_decode($current, true);
      if (is_array($decoded)) {
        $asArray = $decoded;
      }
    } elseif (is_array($current)) {
      $asArray = $current;
    }

    // Normalize to list of events
    if (!isset($asArray['events']) || !is_array($asArray['events'])) {
      $asArray['events'] = [];
    }

    $asArray['events'][] = $addition + ['at' => Carbon::now()->toIso8601String()];

    return $asArray;
  }

  /**
   * Encode arrays/objects to JSON safely for DB JSON columns (Query Builder).
   * Returns '{}' on failure.
   * @param  mixed  $data
   * @return string
   */
  private function j($data): string
  {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
}
