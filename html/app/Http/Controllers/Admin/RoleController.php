<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolesRequest;
use App\Models\User;
use App\Services\Admin\RoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Controller for managing user roles within a multi-tenant application.
 *
 * Allows authorized users to view and update roles for other users,
 * enforcing strict rules based on user types (platform vs tenant).
 */
class RoleController extends Controller
{
  /** @var RoleService */
  protected RoleService $roleService;

  /**
   * Create a new controller instance.
   */
  public function __construct()
  {
    $this->roleService = new RoleService();
  }

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

    [
      $canEdit,
      $allRoles,
      $allowed,
      $current
    ] = $this->roleService->canEdit($actor, $user);

    if ($canEdit) {
      return view('admin.users.roles', [
        'user'          => $user,
        'allRoles'      => $allRoles,
        'allowedSlugs'  => $allowed->pluck('slug')->all(),
        'currentSlug'   => $current,
      ]);
    } else {
      abort(403, 'You are not allowed to modify roles for this user.');
    }
  }

  /**
   * Update the specified user's roles in storage.
   *
   * @param  UpdateRolesRequest  $request
   * @param  User  $user
   * @return RedirectResponse
   * @throws AuthorizationException
   */
  public function update(UpdateRolesRequest $request, User $user): RedirectResponse
  {
    // Prepare context and authorize
    /** @var User $actor */
    $actor = auth()->user();

    // Gate check (calls UserPolicy::updateRole)
    $this->authorize('updateRole', $user);

    // Delegate context validation to service
    [
      $isValid,
      $vltMsg,
      $requestedRole,
      $targetUserRole
    ] = $this->roleService->validateUpdate($request, $actor, $user);
    if (!$isValid) {
      return back()->with('error', $vltMsg);
    }

    // All checks passed, proceed with the update.
    [$isSaved, $uptMsg] = $this->roleService->update(
      $user,
      $requestedRole,
      $targetUserRole,
      $actor
    );

    return $isSaved
      ? back()->with('success', $uptMsg)
      : back()->with('error', $uptMsg);
  }
}
