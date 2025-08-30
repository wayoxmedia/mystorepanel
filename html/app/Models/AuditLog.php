<?php
// app/Models/AuditLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Class AuditLog
 *
 * Represents an audit log entry in the application.
 *
 * @property int $id
 * @property int $actor_id The ID of the user who performed the action.
 * @property string $action The action performed (e.g., 'created', 'updated', 'deleted').
 * @property string $subject_type The type of subject affected by the action (e.g., 'User', 'Post').
 * @property int $subject_id The ID of the subject affected by the action.
 * @property array $meta Additional metadata related to the action.
 */
class AuditLog extends Model
{
  protected $table = 'audit_logs';

  protected $fillable = [
    'actor_id',      // int|null
    'action',        // string
    'subject_type',  // string|null (FQ Collection name, e.g. App\Models\User)
    'subject_id',    // int|string|null
    'meta',          // json
    'created_at',
    'updated_at',
  ];

  protected $casts = [
    'meta'       => AsArrayObject::class,
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  /**
   * Enrich automatically meta on create:
   * - impersonator_id (if impersonating)
   * - actor_tenant_id (if actor has tenant)
   * - ip and user_agent (if Request available)
   *
   * Note: does not overwrite existing keys if you already passed them in meta.
   */
  protected static function booted(): void
  {
    static::creating(function (self $log) {
      $meta = ($log->meta ?? []);

      // Impersonation
      $impersonatorId = null;
      try {
        if (function_exists('session') && session()->has('impersonator_id')) {
          $impersonatorId = session()->get('impersonator_id');
        }
      } catch (Throwable $e) {
        // session not available (cli), ignorar
      }

      if ($impersonatorId !== null && ! Arr::has($meta, 'impersonator_id')) {
        $meta['impersonator_id'] = (int) $impersonatorId;
      }

      // Actor tenant (if actor id is set and actor has tenant)
      if (! Arr::has($meta, 'actor_tenant_id') && ! empty($log->actor_id)) {
        try {
          $actor = User::query()->find($log->actor_id);
          if ($actor && ! is_null($actor->tenant_id)) {
            $meta['actor_tenant_id'] = (int) $actor->tenant_id;
          }
        } catch (Throwable $e) {
          // do not break logging if something goes wrong
        }
      }

      // Request context
      try {
        $request = request(); // may throw if no request (cli)
        if ($request) {
          if (! Arr::has($meta, 'ip')) {
            $meta['ip'] = $request->ip();
          }
          if (! Arr::has($meta, 'user_agent')) {
            $ua = (string) $request->userAgent();
            if ($ua !== '') {
              $meta['user_agent'] = $ua;
            }
          }
        }
      } catch (Throwable $e) {
        // probably CLI; ignore
      }

      $log->meta = $meta;
    });
  }
}
