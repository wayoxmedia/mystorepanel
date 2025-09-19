<?php

namespace App\Services\Admin;

use App\Http\Requests\Admin\UpdateRolesRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class RoleService
 */
class RoleService
{
  /**
   * Create a new class instance.
   */
  public function __construct()
  {
    //
  }

  /**
   * Determine if the actor can edit the target user's roles.
   *
   * @param  User  $actor The user performing the action
   * @param  User  $user The target user whose roles are to be edited
   * @return array
   */
  public function canEdit(User $actor, User $user): array
  {
    // Thick authorization: same criteria as for managing users
    if (! $this->canManageTarget($actor, $user)) {
      return [false];
    }

    // Roles disponibles
    $allRoles = Role::query()->orderBy('scope')->orderBy('name')->get();

    // Filtra lo que el actor puede tocar
    $allowed = $allRoles->filter(function ($role) use ($actor, $user) {
      if ($actor->isPlatformSuperAdmin()) {
        return true; // All roles
      }
      if ($role->scope === 'platform') {
        return false;   // tenant manager no toca platform
      }
      // evitar tocar platform SA desde tenant
      if ($user->isPlatformSuperAdmin()) {
        return false;
      }
      // y además el actor solo dentro de su tenant
      return (int) $actor->tenant_id === (int) $user->tenant_id;
    });

    $current = $user->role()->pluck('slug')->first();

    return [true, $allRoles, $allowed, $current];
  }

  /**
   * Validate a role update request.
   *
   * @param  UpdateRolesRequest  $request
   * @param  User  $actor The user performing the action
   * @param  User  $user The target user whose roles are to be updated
   * @return array [bool success, string message, Role|null newRole, Role|null oldRole]
   */
  public function validateUpdate(UpdateRolesRequest $request, User $actor, User $user): array
  {
    // Thick authorization: same criteria as for managing users
    if (!$this->canManageTarget($actor, $user)
      || !$actor->can('manage-user-roles', $user)
    ) {
      return [false, 'You are not allowed to modify roles for this user.'];
    }

    // You can't change your own roles, avoid privilege escalation and lockout.
    if ($actor->id === $user->id) {
      return [false, 'You cannot change your own roles here.'];
    }

    // Get the role from BD.
    /** @var Role $requestedRole */
    $requestedRole = Role::query()
      ->where('slug', $request->roleSlug())
      ->get(['id', 'name', 'slug', 'scope'])
      ->first();

    // No changes? Exit early.
    $targetUserRole = $user->role;
    if ($targetUserRole->id === $requestedRole->id) {
      return [false, 'Same roles. No changes to apply.'];
    }

    // Consistency rules by user type (tenant vs platform).
    $isUserPSA = is_null($user->tenant_id);
    $isActorSA = $actor->isPlatformSuperAdmin();

    if ($isUserPSA) {
      $expectedScope = 'platform';
      $errorMessage = 'Platform user cannot have tenant role: %s';
    } else {
      $expectedScope = 'tenant';
      $errorMessage = 'Tenant user cannot have platform role: %s';
    }

    if ($requestedRole->scope != $expectedScope) {
      return [false, sprintf($errorMessage, $expectedScope)];
    }

    // No one can assign 'platform_super_admin' unless SA and target is platform staff
    if ($requestedRole->scope === 'platform_super_admin') {
      if (! $isActorSA || ! $isUserPSA) {
        return [
          false,
          'Only Platform Super Admins can assign the Platform Super Admin role to MSP staff.'
        ];
      }
    }

    // Invariant: do not leave the tenant without a "tenant_owner"
    // - Only applies to tenant users
    if (!$isUserPSA) {
      // TODO Should we match a constant or config value
      //   for 'tenant_owner' and their DB ID? May save a query.
      $ownerRoleId = Role::query()
        ->where('slug', 'tenant_owner')
        ->value('id');

      // If was tenant_owner and will not be anymore,
      // check there's at least another owner in the tenant
      $currentOwners = User::query()
        ->where('role_id', $ownerRoleId)
        ->where('tenant_id', $user->tenant_id) // same tenant
        ->where('id', '!=', $user->id) // exclude current user
        ->count();

      if ($currentOwners === 0 // last owner
        && $user->role_id === $ownerRoleId // was owner
        && $requestedRole->id !== $ownerRoleId // will not be owner anymore
      ) {
        return [
          false,
          'You cannot remove the last Tenant Owner from this tenant.'
        ];
      }
    }

    return [
      true,
      'Roles Validated successfully.',
      $requestedRole,
      $targetUserRole
    ];
  }

  /**
   * Update User Roles.
   *
   * Persist changes and audit.
   * @param  User $user
   * @param  Role $requestedRole
   * @param  Role $targetUserRole
   * @param  User $actor
   * @return array [bool success, string message]
   */
  public function update(
    User $user,
    Role $requestedRole,
    Role $targetUserRole,
    User $actor
  ): array {
    try {
      DB::transaction(
        function () use ($user, $requestedRole, $targetUserRole, $actor) {
          $user->role_id = $requestedRole->id;
          $user->save();

          AuditLog::query()->create([
            'actor_id'     => $actor->id,
            'action'       => 'user.roles.updated',
            'subject_type' => User::class,
            'subject_id'   => $user->id,
            'meta'         => [
              'from' => $targetUserRole->name,
              'to'   => $requestedRole->name,
              'tenant_id' => $user->tenant_id,
            ],
          ]);
        });
    } catch (Throwable $e) {
      Log::error('RoleService::update error: ' . $e->getMessage());
      return [
        false,
        'Unable to update roles: ' . $e->getMessage()
      ];
    }
    return [
      true, 'Roles updated successfully.'
    ];
  }

  /**
   * Determine if the actor can manage the target user.
   * @param  User $actor
   * @param  User $target
   * @return boolean
   */
  private function canManageTarget(User $actor, User $target): bool
  {
    if ($actor->isPlatformSuperAdmin()) {
      return true;
    }

    $isTenantManager = $actor->hasAnyRole(['tenant_owner','tenant_admin']);
    $sameTenant      = (int)$actor->tenant_id === (int)$target->tenant_id;

    // Nunca permitir que un tenant manager toque a un Platform SA
    if ($target->isPlatformSuperAdmin()) {
      return false;
    }

    return $isTenantManager && $sameTenant;
  }
}
