<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * TenantUsersController
 *
 * Purpose:
 * - Administer *users within a tenant* for sensitive operations (status/role changes).
 * - Keep controllers thin (validation + orchestration) and delegate cross-cutting
 *   concerns (audit) to services.
 *
 * Security model (enforced in routes, not here):
 * - 'auth:api'             : JWT is required
 * - 'active'               : user.status === 'active' (your enum)
 * - 'email.verified'       : email must be verified (optional if blocked at login)
 * - 'role:tenant_admin'    : only tenant_admin (and above by hierarchy) or PSA
 * - 'reauth'               : a recent POST /auth/reauth was performed (short window)
 *
 * Notes:
 * - Route model binding: {user} -> User $user
 * - Tenant scoping: non-platform admins can only operate within their own tenant.
 * - Super admin hard rules: only platform_super_admin
 * can modify another platform_super_admin.
 */
class TenantUsersController extends Controller
{
  /**
   * PATCH /tenants/{tenant_id}/users/{user}/status
   *
   * Body:
   *  - status: one of ['active','pending_invite','suspended','locked']
   *
   * Returns:
   *  - 200 { ok: true, user_id, tenant_id, new: { status } }
   *  - 403 on cross-tenant access (unless platform_super_admin)
   *  - 403 when trying to modify a platform_super_admin without being one
   * @param  Request  $request
   * @param  int  $tenant_id
   * @param  User  $user
   * @return JsonResponse
   * @throws ValidationException
   */
  public function updateStatus(Request $request, int $tenant_id, User $user): JsonResponse
  {
    $actor = $request->user();

    // Cross-tenant guard: only platform_super_admin can act across tenants.
    if (!$actor->isPlatformSuperAdmin() && (int)$user->tenant_id !== $tenant_id) {
      return response()->json(
        ['message' => 'Forbidden: cross-tenant'],
        403
      );
    }

    // Protect platform super admin accounts from non-super admins.
    if ($user->getRoleCode() === 'platform_super_admin' && !$actor->isPlatformSuperAdmin()) {
      return response()->json(
        ['message' => 'Forbidden: cannot modify platform super admin'],
        403
      );
    }

    // Validate against your enum.
    $data = $request->validate([
      'status' => [
        'required',
        Rule::in(['active', 'pending_invite', 'suspended', 'locked'])
      ],
    ]);

    // No-op short-circuit if nothing changes.
    $before = ['status' => $user->status];
    if ($before['status'] === $data['status']) {
      return response()->json([
        'ok' => true,
        'note' => 'No changes applied (same status).',
        'status' => $user->status,
      ]);
    }

    // Persist the change.
    $user->status = $data['status'];
    $user->save();

    // Audit (store target tenant for aggregation).
    AuditLogger::for($actor)
      ->on($user)
      ->inTenant($tenant_id)
      ->action('user.status_changed')
      ->changesFrom(
        $before,
        ['status' => $user->status],
        ['status']
      )
      ->meta([
        'request' => ['source' => 'api'], // optional: override/extend request meta
      ])
      ->save();

    return response()->json([
      'ok' => true,
      'user_id' => $user->id,
      'tenant_id' => $tenant_id,
      'new' => ['status' => $user->status],
    ]);
  }

  /**
   * PATCH /tenants/{tenant_id}/users/{user}/role
   *
   * Body:
   *  - role_id: integer (must exist in config('roles.role_map'))
   *
   * Returns:
   *  - 200 { ok: true, user_id, tenant_id, new: { role_id, role } }
   *  - 403 on cross-tenant access (unless platform_super_admin)
   *  - 403 when trying to modify a platform_super_admin without being one
   *
   * Implementation details:
   * - Validates role_id against config('roles.role_map') keys.
   * - Enforces invariant: if new role is platform_super_admin,
   * tenant_id must be NULL.
   * - Writes a clear audit trail with both numeric and string role forms.
   * @param  Request  $request
   * @param  int  $tenant_id
   * @param  User  $user
   * @return JsonResponse
   * @throws ValidationException
   */
  public function updateRole(Request $request, int $tenant_id, User $user): JsonResponse
  {
    $actor = $request->user();

    // Cross-tenant guard.
    if (!$actor->isPlatformSuperAdmin() && (int)$user->tenant_id !== $tenant_id) {
      return response()->json(
        ['message' => 'Forbidden: cross-tenant'],
        403
      );
    }

    // Protect platform super admin target.
    if ($user->getRoleCode() === 'platform_super_admin' && !$actor->isPlatformSuperAdmin()) {
      return response()->json(
        ['message' => 'Forbidden: cannot modify platform super admin'],
        403
      );
    }

    // Validate role_id using your roles map.
    $rolesMap = (array)config('roles.role_map', []);
    $validRoleIds = array_keys($rolesMap);

    $data = $request->validate([
      'role_id' => ['required', 'integer', Rule::in($validRoleIds)],
    ]);

    // No-op if the role_id is unchanged.
    $before = ['role_id' => (int)$user->role_id];
    if ($before['role_id'] === (int)$data['role_id']) {
      return response()->json([
        'ok' => true,
        'note' => 'No changes applied (same role_id).',
        'role_id' => $user->role_id,
        'role' => $rolesMap[$user->role_id] ?? null,
      ]);
    }

    // Persist the change (and enforce platform super admin invariant).
    $user->role_id = (int)$data['role_id'];

    // If the new role is platform_super_admin, enforce tenant_id = null as a safety net.
    if (($rolesMap[$user->role_id] ?? null) === 'platform_super_admin') {
      $user->tenant_id = null;
    }

    $user->save();

    // Build rich audit changes (numeric and code forms).
    $after = ['role_id' => (int)$user->role_id];
    $changes = [
      'role_id' => ['old' => $before['role_id'], 'new' => $after['role_id']],
      'role' => [
        'old' => $rolesMap[$before['role_id']] ?? null,
        'new' => $rolesMap[$after['role_id']] ?? null,
      ],
    ];

    AuditLogger::for($actor)
      ->on($user)
      ->inTenant($tenant_id)
      ->action('user.role_changed')
      ->changes($changes)
      ->meta([
        'request' => ['source' => 'api'],
      ])
      ->save();

    return response()->json([
      'ok' => true,
      'user_id' => $user->id,
      'tenant_id' => $tenant_id,
      'new' => [
        'role_id' => $user->role_id,
        'role' => $rolesMap[$user->role_id] ?? null,
      ],
    ]);
  }
}
