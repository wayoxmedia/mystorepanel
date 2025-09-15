<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Controller for managing user status within the admin panel.
 *
 * Allows authorized users to update the status of other users,
 * with appropriate permission checks and audit logging.
 */
class UserStatusController extends Controller
{
  /**
   * Update the status of a user.
   *
   * Only Platform Super Admins and Tenant Admins/Owners can perform this action,
   * with restrictions on managing users from other tenants or Platform SA.
   * Users cannot change their own status to prevent accidental lockout.
   * Changes are logged in the audit log.
   *
   * @param UpdateUserStatusRequest $request The validated request containing the new status and reason.
   * @param User                    $user    The user whose status is to be updated.
   * @return RedirectResponse Redirects back with success or error message.
   */
  public function update(UpdateUserStatusRequest $request, User $user): RedirectResponse
  {
    /** @var User $actor */
    $actor = auth()->user();

    if (! $this->canManageTarget($actor, $user)) {
      abort(403);
    }

    // No permitirte cambiar tu propio estado (evita lockout accidental)
    if ($actor->id === $user->id) {
      return back()->with('error', 'You cannot change your own status.');
    }

    $newStatus = $request->string('status');
    $reason    = $request->input('reason');

    if ($user->status == $newStatus) {
      return back()->with('success', 'No changes. Status already '.$newStatus.'.');
    }

    $before = $user->status;
    $user->status = $newStatus;

    // Si se suspende o bloquea, invalida "remember me"
    if (in_array($newStatus, ['suspended', 'locked'], true) && method_exists($user, 'setRememberToken')) {
      $user->setRememberToken(Str::random(60));
    }

    $user->save();

    AuditLog::query()->create([
      'actor_id'     => $actor->id,
      'action'       => 'user.status.updated',
      'subject_type' => User::class,
      'subject_id'   => $user->id,
      'meta'         => ['from' => $before, 'to' => $newStatus, 'reason' => $reason],
    ]);

    return back()->with('success', 'User status updated to '.$newStatus.'.');
  }

  /** --- helpers --- */
  /**
   * Determine if the actor can manage the target user.
   *
   * Platform Super Admins can manage anyone.
   * Tenant Admins/Owners can manage users within their own tenant, except Platform SA.
   *
   * @param User $actor  The user performing the action.
   * @param User $target The user being acted upon.
   * @return boolean True if the actor can manage the target, false otherwise.
   */
  private function canManageTarget(User $actor, User $target): bool
  {
    if ($actor->isPlatformSuperAdmin()) {
      return true;
    }

    $isTenantManager = $actor->hasAnyRole(['tenant_owner','tenant_admin']);
    $sameTenant      = (int)$actor->tenant_id === (int)$target->tenant_id;

    // Un tenant manager no puede actuar sobre un Platform SA
    if ($target->isPlatformSuperAdmin()) {
      return false;
    }

    return $isTenantManager && $sameTenant;
  }
}
