<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserRoleController extends Controller
{
  public function edit(User $user): View
  {
    $actor = auth()->user();

    // Autorización gruesa: mismo criterio que para gestionar usuarios
    if (! $this->canManageTarget($actor, $user)) {
      abort(403);
    }

    // Roles disponibles
    $allRoles = Role::orderBy('scope')->orderBy('name')->get();

    // Filtra lo que el actor puede tocar
    $allowed = $allRoles->filter(function ($role) use ($actor, $user) {
      if ($actor->isPlatformSuperAdmin()) return true; // todo
      if ($role->scope === 'platform') return false;   // tenant manager no toca platform
      // evitar tocar platform SA desde tenant
      if ($user->isPlatformSuperAdmin()) return false;
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

  public function update(UpdateUserRolesRequest $request, User $user): RedirectResponse
  {
    $actor = auth()->user();

    if (! $this->canManageTarget($actor, $user)) {
      abort(403);
    }

    // No te permito editarte a ti mismo para evitar lockouts accidentales
    if ($actor->id === $user->id) {
      return back()->with('error', 'You cannot change your own roles here.');
    }

    $submitted = collect($request->input('role_slugs', []))->unique()->values();

    // Filtra según permisos del actor
    $allowed = Role::whereIn('slug', $submitted)->get()->filter(function ($role) use ($actor, $user) {
      if ($actor->isPlatformSuperAdmin()) return true;
      if ($role->scope === 'platform') return false;
      if ($user->isPlatformSuperAdmin()) return false;
      return (int)$actor->tenant_id === (int)$user->tenant_id;
    });

    // Role IDs a aplicar
    $newRoleIds = $allowed->pluck('id')->all();

    // Calcula diff para auditoría
    $before = $user->roles()->pluck('slug')->all();
    $user->roles()->sync($newRoleIds);
    $after  = $user->roles()->pluck('slug')->all();

    $added   = array_values(array_diff($after, $before));
    $removed = array_values(array_diff($before, $after));

    AuditLog::create([
      'actor_id'     => $actor->id,
      'action'       => 'user.roles.synced',
      'subject_type' => User::class,
      'subject_id'   => $user->id,
      'meta'         => ['added' => $added, 'removed' => $removed],
    ]);

    return redirect()
      ->route('admin.users.roles.edit', $user)
      ->with('success', 'Roles updated.');
  }

  /** --- helpers --- */
  private function canManageTarget(User $actor, User $target): bool
  {
    if ($actor->isPlatformSuperAdmin()) return true;

    $isTenantManager = $actor->hasAnyRole(['tenant_owner','tenant_admin']);
    $sameTenant      = (int)$actor->tenant_id === (int)$target->tenant_id;

    // Nunca permitir que un tenant manager toque a un Platform SA
    if ($target->isPlatformSuperAdmin()) return false;

    return $isTenantManager && $sameTenant;
  }
}
