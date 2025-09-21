<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request as RequestFacade;
use InvalidArgumentException;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * AuditLogger
 *
 * Purpose:
 * - Standardize how we write to `audit_logs` without changing the DB schema.
 * - Always include consistent metadata
 * (tenant, actor, request, reauth flag, changes).
 *
 * Usage (fluent):
 *   AuditLogger::for($actor)
 *     // optional: Model or ['type' => FQCN, 'id' => 123]
 *     ->on($targetModelOrArray)
 *     // tenant of the *affected* entity (target)
 *     ->inTenant($targetTenantId)
 *     // stable action key
 *     ->action('user.status_changed')
 *     // only relevant keys
 *     ->changesFrom($before, $after, ['status','role_id'])
 *     // optional additions to meta
 *     ->meta(['note' => 'extra info'])
 *     ->save();
 *
 * Quick static (one-shot):
 *   AuditLogger::log(
 *     $actor,
 *     'user.role_changed',
 *     $target,
 *     ['role_id' => ['old' => 5, 'new' => 3]],
 *     ['source' => 'api']
 *   );
 */
class AuditLogger
{
  /**
   * Authenticated user id or null (system)
   * @var int|null $actorId
   */
  protected ?int $actorId = null;

  /**
   * Normalized role code (e.g., tenant_admin)
   * @var string|null $actorRole
   */
  protected ?string $actorRole = null;

  /**
   * Actor tenant id (null for platform_super_admin)
   *
   * @var int|null $actorTenantId
   */
  protected ?int $actorTenantId = null;

  /**
   * Stable action key, e.g. user.status_changed
   *
   * @var string|null $action
   */
  protected ?string $action = null;

  /**
   * FQCN of affected model (e.g., App\Models\User)
   * @var string|null $subjectType
   */
  protected ?string $subjectType = null;

  /**
   * Primary key of affected model
   *
   * @var int|null $subjectId
   */
  protected ?int $subjectId = null;

  /**
   * Tenant id of the *affected* entity (target)
   *
   * @var int|null $targetTenantId
   */
  protected ?int $targetTenantId = null;

  /**
   * Only changed fields: ['field' => ['old' => ..., 'new' => ...], ...]
   *
   * @var array $changes
   */
  protected array $changes = [];

  /**
   * Extra metadata to merge
   *
   * @var array $meta
   */
  protected array $meta = [];

  /**
   * Start a new audit for a given actor.
   * $actor can be null to represent system jobs/scheduler.
   * @param Authenticatable|null $actor
   * @return $this
   */
  public static function for(?Authenticatable $actor): self
  {
    $instance = new self();

    if ($actor) {
      $instance->actorId = method_exists($actor, 'getAuthIdentifier')
        ? (int) $actor->getAuthIdentifier()
        : (int) (($actor->id ?? 0) ?: null);

      // If your User model has the trait with getRoleCode(), read it; otherwise null.
      $instance->actorRole = method_exists($actor, 'getRoleCode')
        ? (string) $actor->getRoleCode()
        : null;

      // Actor tenant (if present in your User schema)
      $instance->actorTenantId = isset($actor->tenant_id) ? (int) $actor->tenant_id : null;
    }

    return $instance;
  }

  /**
   * Optional: set the affected subject (model or ['type' => FQCN, 'id' => int]).
   *
   * If a Model is provided and it has tenant_id, we use it as a sensible default
   * for the target tenant (can be overridden via ->inTenant()).
   * @param Model|array|null $subject
   * @return $this
   */
  public function on(Model|array|null $subject): self
  {
    if ($subject instanceof Model) {
      $this->subjectType = get_class($subject);
      $this->subjectId   = (int) $subject->getKey();

      // If the subject itself exposes tenant_id, use it as a sensible default
      if ($this->targetTenantId === null && isset($subject->tenant_id)) {
        $this->targetTenantId = (int) $subject->tenant_id;
      }
    } elseif (is_array($subject)) {
      $this->subjectType = isset($subject['type']) ? (string) $subject['type'] : null;
      $this->subjectId   = isset($subject['id']) ? (int) $subject['id'] : null;
    }

    return $this;
  }

  /**
   * Explicitly set the tenant of the *affected* entity (target).
   *
   * This is what we'll store in meta.tenant_id to aggregate audits by tenant.
   * @param int|null $tenantId Null for platform-level actions.
   * @return $this
   */
  public function inTenant(?int $tenantId): self
  {
    $this->targetTenantId = $tenantId;
    return $this;
  }

  /**
   * Set the stable action key (e.g., "user.status_changed").
   * This is required.
   * @param string $action
   * @return $this
   */
  public function action(string $action): self
  {
    $this->action = $action;
    return $this;
  }

  /**
   * Provide already-computed changes
   * (['field' => ['old' => v1, 'new' => v2]]).
   * Will redact sensitive values automatically.
   * @param array $changes
   * @return $this
   */
  public function changes(array $changes): self
  {
    $this->changes = $this->redact($changes);
    return $this;
  }

  /**
   * Compute changes from two arrays (before vs after).
   * Optionally limit to a whitelist of keys (e.g., ['status','role_id']).
   * Will redact sensitive values automatically.
   * Example:
   *  - $before = ['status' => 'active', 'role_id' => 3, 'name' => 'Alice'];
   *  - $after  = ['status' => 'suspended', 'role_id' => 3, 'name' => 'Alice B'];
   *  - ->changesFrom($before, $after, ['status'])
   *
   *  results in:
   *  - ['status' => ['old' => 'active', 'new' => 'suspended']]
   *
   * Note: if you want to track all changes,
   * just call ->changesFrom($before, $after)
   * without the third arg.
   * @param array $before
   * @param array $after
   * @param array|null $onlyKeys
   * @return $this
   */
  public function changesFrom(
    array $before,
    array $after,
    ?array $onlyKeys = null
  ): self
  {
    $keys = $onlyKeys ?: array_unique(
      array_merge(
        array_keys($before),
        array_keys($after)
      )
    );
    $diff = [];

    foreach ($keys as $key) {
      $old = Arr::get($before, $key);
      $new = Arr::get($after, $key);

      // Strict comparison to avoid false positives
      // (e.g., "1" vs 1 if you care about type)
      if ($old !== $new) {
        $diff[$key] = ['old' => $old, 'new' => $new];
      }
    }

    $this->changes = $this->redact($diff);
    return $this;
  }

  /**
   * Add extra metadata keys (merged later).
   * Example: ['source' => 'api', 'note' => 'manual override']
   * @param array $extra
   * @return $this
   */
  public function meta(array $extra): self
  {
    $this->meta = array_merge($this->meta, $extra);
    return $this;
  }

  /**
   * Persist the audit row using the existing AuditLog model.
   * Returns the created AuditLog instance.
   * @throws InvalidArgumentException if action is not set.
   * @return AuditLog
   */
  public function save(): AuditLog
  {
    $payload = [
      'actor_id'     => $this->actorId,
      'action'       => (string) $this->action,
      'subject_type' => $this->subjectType,
      'subject_id'   => $this->subjectId,
      'meta'         => $this->buildMeta(), // array; Eloquent casts to JSON if configured
    ];

    // Minimal safety checks
    if ($payload['action'] === '') {
      throw new InvalidArgumentException('AuditLogger: action is required.');
    }

    return AuditLog::query()->create($payload);
  }

  /**
   * One-shot static helper for convenience.
   *
   * - $subject can be a Model or ['type' => FQCN, 'id' => int].
   * - $changes should follow the ['field' => ['old' => ..., 'new' => ...]] shape.
   * - $extraMeta is optional (e.g., ['source' => 'api']).
   * - $targetTenantId is the tenant of the *affected* entity (target).
   *
   * Returns the created AuditLog instance.
   * @param Authenticatable|null $actor
   * @param string $action
   * @param Model|array|null $subject
   * @param array $changes
   * @param array $extraMeta
   * @param int|null $targetTenantId
   * @return AuditLog
   * @throws InvalidArgumentException
   */
  public static function log(
    ?Authenticatable $actor,
    string $action,
    Model|array|null $subject = null,
    array $changes = [],
    array $extraMeta = [],
    ?int $targetTenantId = null
  ): AuditLog {
    return self::for($actor)
      ->action($action)
      ->on($subject)
      ->inTenant($targetTenantId)
      ->changes($changes)
      ->meta($extraMeta)
      ->save();
  }

  // -----------------------
  // Internals
  // -----------------------

  /**
   * Build the `meta` array consistently.
   * - meta.tenant_id  => tenant of the affected entity (target)
   * - meta.actor      => { id, role, tenant_id }
   * - meta.request    => { id, ip, ua, source }
   * - meta.reauth     => bool (recent /auth/reauth flag bound to this JWT)
   * - meta.changes    => diff of fields (if provided)
   */
  protected function buildMeta(): array
  {
    $request = $this->safeRequest();
    $source  = php_sapi_name() === 'cli' ? 'job' : 'api';

    $meta = [
      // target tenant (important for multi-tenant aggregation)
      'tenant_id' => $this->targetTenantId,
      'actor'     => [
        'id'        => $this->actorId,
        'role'      => $this->actorRole,     // e.g., tenant_admin, platform_super_admin
        'tenant_id' => $this->actorTenantId, // null for platform_super_admin
      ],
      'request'   => [
        'id'   => $request?->headers->get('X-Request-Id') ?: null,
        'ip'   => $request?->ip(),
        'ua'   => $request?->userAgent(),
        // api | job | webhook (if you want, you can override via ->meta([...]))
        'source' => $source,
      ],
      'reauth'    => $this->checkRecentReauth(),
    ];

    if (!empty($this->changes)) {
      $meta['changes'] = $this->changes;
    }

    // Merge any additional meta provided by the caller (caller can override or extend)
    if (!empty($this->meta)) {
      $meta = array_replace_recursive($meta, $this->meta);
    }

    return $meta;
  }

  /**
   * Check if the current request has a recent /auth/reauth flag.
   *
   * We bind it to the user id + sha1(current JWT) like the reauth controller/middleware.
   *
   * Returns true if found, false otherwise.
   * If no actor or no JWT, returns false.
   * @return boolean
   */
  protected function checkRecentReauth(): bool
  {
    if ($this->actorId === null) {
      return false;
    }

    // Try to pull the same JWT we used in the Reauth flow
    $token = (string) (JWTAuth::getToken() ?: '');
    if ($token === '') {
      $req = $this->safeRequest();
      $bearer = $req?->bearerToken();
      if ($bearer) {
        $token = $bearer;
      }
    }

    if ($token === '') {
      return false;
    }

    $key = sprintf('reauth:%d:%s', $this->actorId, sha1($token));
    return Cache::has($key);
  }

  /**
   * Safely get the current Request (null in CLI contexts).
   * Catches any Throwable to avoid breaking in non-HTTP contexts.
   * @return Request|null
   */
  protected function safeRequest(): ?Request
  {
    try {
      return RequestFacade::instance();
    } catch (Throwable) {
      return null;
    }
  }

  /**
   * Redact sensitive values inside the changes array.
   * - Masks known sensitive keys (password, token, secret, api_key, authorization).
   * - Apply recursively if needed.
   * - Also handles the common shape ['field' => ['old' => ..., 'new' => ...]].
   * @param array $changes
   * @return array
   */
  protected function redact(array $changes): array
  {
    $sensitive = [
      'password',
      'pwd',
      'token',
      'secret',
      'api_key',
      'authorization',
      'authorization_header'
    ];
    $mask = fn($v) => is_string($v) && $v !== ''
      ? str_repeat(
        '*',
        min(
          12,
          max(
            6,
            (int) ceil(
              strlen($v) * 0.6
            )
          )
        )
      )
      : '***';

    $walker = function (&$value, $key) use ($sensitive, $mask, &$walker) {
      if (is_array($value)) {
        array_walk($value, $walker);
        return;
      }
      $needsMask = in_array(
        strtolower((string)$key),
        $sensitive, true
      );
      // Only mask when key hints at sensitive data
      if ($needsMask) {
        $value = $mask((string)$value);
      }
    };

    $copy = $changes;
    array_walk($copy, $walker);

    // Also handle shape ['field' => ['old' => ..., 'new' => ...]]
    foreach ($copy as $field => &$pair) {
      $checkForPair = is_array($pair) &&
        array_key_exists('old', $pair) &&
        array_key_exists('new', $pair);

      if ($checkForPair) {
        $checkForSensitive = in_array(
          strtolower((string) $field),
          $sensitive, true
        );
        if ($checkForSensitive) {
          $pair['old'] = $mask((string)$pair['old']);
          $pair['new'] = $mask((string)$pair['new']);
        }
      }
    }

    return $copy;
  }
}
