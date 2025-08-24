<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class UserStatusController extends Controller
{
  public function update(UpdateUserStatusRequest $request, User $user): RedirectResponse
  {
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

    if ($user->status === $newStatus) {
      return back()->with('success', 'No changes. Status already '.$newStatus.'.');
    }

    $before = $user->status;
    $user->status = $newStatus;

    // Si se suspende o bloquea, invalida "remember me"
    if (in_array($newStatus, ['suspended', 'locked'], true) && method_exists($user, 'setRememberToken')) {
      $user->setRememberToken(Str::random(60));
    }

    $user->save();

    AuditLog::create([
      'actor_id'     => $actor->id,
      'action'       => 'user.status.updated',
      'subject_type' => User::class,
      'subject_id'   => $user->id,
      'meta'         => ['from' => $before, 'to' => $newStatus, 'reason' => $reason],
    ]);

    return back()->with('success', 'User status updated to '.$newStatus.'.');
  }

  /** --- helpers --- */
  private function canManageTarget(User $actor, User $target): bool
  {
    if ($actor->isPlatformSuperAdmin()) return true;

    $isTenantManager = $actor->hasAnyRole(['tenant_owner','tenant_admin']);
    $sameTenant      = (int)$actor->tenant_id === (int)$target->tenant_id;

    // Un tenant manager no puede actuar sobre un Platform SA
    if ($target->isPlatformSuperAdmin()) return false;

    return $isTenantManager && $sameTenant;
  }
}
