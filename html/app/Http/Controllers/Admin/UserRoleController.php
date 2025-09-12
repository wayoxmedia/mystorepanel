<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Controller for managing user roles within a multi-tenant application.
 *
 * Allows authorized users to view and update roles for other users,
 * enforcing strict rules based on user types (platform vs tenant).
 */
class UserRoleController extends Controller
{

  /**
   * Show the form for editing the specified user's roles.
   *
   * @param  User  $user
   * @return View
   */
  public function edit(User $user): View
  {
    /** @var User $actor */
    $actor = auth()->user();

    // Thick authorization: same criteria as for managing users
    if (! $this->canManageTarget($actor, $user)) {
      abort(403);
    }

    // Roles disponibles
    $allRoles = Role::query()->orderBy('scope')->orderBy('name')->get();

    // Filtra lo que el actor puede tocar
    $allowed = $allRoles->filter(function ($role) use ($actor, $user) {
      if ($actor->isPlatformSuperAdmin()) {
        return true; // todo
      }
      if ($role->scope === 'platform') {
        return false;   // tenant manager no toca platform
      }
      // evitar tocar platform SA desde tenant
      if ($user->isPlatformSuperAdmin()) {
        return false;
      }
      // y además el actor solo dentro de su tenant
      return (int)$actor->tenant_id === (int)$user->tenant_id;
    });

    $current = $user->roles->pluck('slug')->all();

    return view('admin.users.roles', [
      'user'          => $user,
      'allRoles'      => $allRoles,
      'allowedSlugs'  => $allowed->pluck('slug')->all(),
      'currentSlugs'  => $current,
    ]);
  }

  /**
   * Update the specified user's roles in storage.
   *
   * @param  UpdateUserRolesRequest  $request
   * @param  User  $user
   * @return RedirectResponse
   */
  public function update(UpdateUserRolesRequest $request, User $user): RedirectResponse
  {
    /** @var User $actor */
    $actor = auth()->user();

    if (!$this->canManageTarget($actor, $user)
      || !$actor->can('manage-user-roles', $user)
    ) {
      return back()->with('error', 'You are not allowed to modify roles for this user.');
    }

    // You can't change your own roles, avoid privilege escalation and lockout.
    if ($actor->id === $user->id) {
      return back()->with('error', 'You cannot change your own roles here.');
    }

    // Input Validation.
    $data = $request->validate([
      'roles'   => ['required','array'],
      'roles.*' => ['string','exists:roles,slug'],
    ]);


    // Requested roles.
    $incomingSlugs = collect($data['roles'])
      ->filter()
      ->unique()
      ->values();

    // Get roles from BD.
    $roles = Role::query()
      ->whereIn('slug', $incomingSlugs)
      ->get(['id','slug','scope']);

    // Consistency rules by user type (tenant vs platform).
    $isStaffTarget  = is_null($user->tenant_id);
    $isActorSA      = $actor->isPlatformSuperAdmin();

    if ($isStaffTarget) {
      $expectedScope = 'platform';
      $errorMessage = 'Platform user cannot have tenant role: %s';
    } else {
      $expectedScope = 'tenant';
      $errorMessage = 'Tenant user cannot have platform role: %s';
    }
    /** @var Role $invalid */
    $invalid = $roles->firstWhere('scope', '!=', $expectedScope);
    if ($invalid) {
      return back()->with('error', sprintf($errorMessage, $invalid->slug));
    }

    // No one can assign 'platform_super_admin' unless SA and target is platform staff
    if ($roles->contains(fn($r) => $r->slug === 'platform_super_admin')) {
      if (! $isActorSA || ! $isStaffTarget) {
        return back()
          ->with('error', 'Only Platform Super Admins can assign the Platform Super Admin role to platform staff.');
      }
    }

    // Invariant: do not leave the tenant without a "tenant_owner"
    // - Only applies to tenant users
    if (!$isStaffTarget) {
      $ownerRoleId = Role::query()
        ->where('slug', 'tenant_owner')
        ->value('id');

      if ($ownerRoleId) {
        $currentOwner = DB::table('role_user')
          ->where('user_id', $user->id)
          ->where('role_id', $ownerRoleId)
          ->exists();

        $incomingOwner = $roles->contains(fn($r) => (int)$r->id === (int)$ownerRoleId);

        // If was owner and will not be, check there's at least another owner in the tenant
        if ($currentOwner && ! $incomingOwner) {
          $otherOwners = DB::table('role_user as ru')
            ->join('users as u', 'u.id', '=', 'ru.user_id')
            ->where('ru.role_id', $ownerRoleId)
            ->where('u.tenant_id', $user->tenant_id)
            ->where('u.id', '!=', $user->id)
            ->count();

          if ($otherOwners === 0) {
            return back()->with('error', 'You cannot remove the last Tenant Owner from this tenant.');
          }
        }
      }
    }

    // No changes? Exit early.
    $currentIds  = $user->roles()->pluck('roles.id')->all();
    $incomingIds = $roles->pluck('id')->all();
    sort($currentIds);
    sort($incomingIds);
    if ($currentIds === $incomingIds) {
      return back()->with('info', 'No changes to apply.');
    }

    // Persist changes and audit.
    try {
      DB::transaction(function () use ($user, $incomingIds, $currentIds, $actor) {
        $user->roles()->sync($incomingIds);

        AuditLog::query()->create([
          'actor_id'     => $actor->id,
          'action'       => 'user.roles.updated',
          'subject_type' => User::class,
          'subject_id'   => $user->id,
          'meta'         => [
            'from' => $currentIds,
            'to'   => $incomingIds,
            'tenant_id' => $user->tenant_id,
          ],
        ]);
      });
    } catch (Throwable $e) {
      return back()->with('error', 'Unable to update roles: '.$e->getMessage());
    }

    return back()->with('success', 'Roles updated.');
  }

  /** --- helpers --- */

  /**
   * @param  User  $actor
   * @param  User  $target
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
