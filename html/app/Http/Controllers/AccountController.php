<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Class AccountController
 *
 * Keep this file outside the Admin namespace.
 * This is for all users to manage their own account, no need to be an Admin user.
 * This is protected by 'auth' middleware in the routes file though.
 */
class AccountController extends Controller
{
  /**
   * Show the user's account details.
   *
   * @param Request $request
   * @return View
   */
  public function show(Request $request): View
  {
    return view('account.show', ['user' => $request->user()]);
  }

  /**
   * Update the user's password.
   *
   * @param Request $request
   * @return RedirectResponse
   */
  public function updatePassword(Request $request): RedirectResponse
  {
    $request->validate([
      'current_password' => ['required'],
      'password' => ['required', 'confirmed', Password::min(10)],
    ]);

    $user = $request->user();

    if (! Hash::check($request->string('current_password'), $user->password)) {
      return back()->withErrors(['current_password' => 'Current password is incorrect.']);
    }

    $user->password = Hash::make($request->string('password'));
    $user->save();

    AuditLog::query()->create([
      'actor_id' => $user->id,
      'action' => 'account.password_changed',
      'subject_type' => get_class($user),
      'subject_id' => $user->id,
      'meta' => null,
    ]);

    return back()->with('success', 'Password updated.');
  }
}
