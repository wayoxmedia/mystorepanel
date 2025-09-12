<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Controller for user impersonation functionality within the admin panel.
 *
 * Allows authorized users to start and stop impersonating other users.
 */
class ImpersonationController extends Controller
{
  /**
   * Start impersonating the given user.
   *
   * @param  User  $user
   * @return RedirectResponse
   */
  public function start(User $user): RedirectResponse
  {
    // If already impersonating, block
    if (session()->has('impersonator_id')) {
      return back()->with('error', 'Already impersonating. Stop current session first.');
    }

    // Auth
    Gate::authorize('impersonate-user', $user);

    // Avoid impersonating yourself
    if (Auth::id() === $user->id) {
      return back()->with('error', 'Cannot impersonate yourself.');
    }

    // Saves actor in the session
    session([
      'impersonator_id'    => Auth::id(),
      'impersonator_email' => Auth::user()->email,
    ]);

    // Audit (register who started impersonation and who is being impersonated)
    AuditLog::query()->create([
      'actor_id'     => session('impersonator_id'),
      'action'       => 'impersonation.start',
      'subject_type' => User::class,
      'subject_id'   => $user->id,
      'meta'         => ['target_email' => $user->email],
    ]);

    // Logs in as the target user
    Auth::login($user, false);
    request()->session()->regenerate();

    return redirect('/')
      ->with('success', 'You are now impersonating '.$user->email);
  }

  /**
   * Stop impersonation and return to original user.
   *
   * @return RedirectResponse
   */
  public function stop(): RedirectResponse
  {
    if (! session()->has('impersonator_id')) {
      return redirect()->route('admin.users.index')
        ->with('error', 'Not impersonating.');
    }

    $impersonatorId    = (int) session('impersonator_id');
    $impersonatedId    = (int) Auth::id();
    $impersonatedEmail = Auth::user()?->email;

    // Audit
    AuditLog::query()->create([
      'actor_id'     => $impersonatorId,   // real actor
      'action'       => 'impersonation.end',
      'subject_type' => User::class,
      'subject_id'   => $impersonatedId,   // to whom we were impersonating
      'meta'         => ['target_email' => $impersonatedEmail],
    ]);

    // Back to original user
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    Auth::loginUsingId($impersonatorId, false);
    request()->session()->regenerate();

    // Cleans up impersonation session data (flags)
    session()->forget(['impersonator_id', 'impersonator_email']);

    return redirect()->route('admin.users.index')
      ->with('success', 'Impersonation ended. You are back as yourself.');
  }
}
