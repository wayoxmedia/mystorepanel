<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * LoginController handles user authentication, login, and logout processes.
 * It includes status checks, email verification enforcement, and audit logging.
 */
class LoginController extends Controller
{
  /**
   * Show the login form.
   * If the user is already authenticated, redirect to the intended route.
   *
   * @return Factory|View|Application|RedirectResponse
   */
  public function showLoginForm(): Factory|View|Application|RedirectResponse
  {
    $checkAuthenticated = auth()->check();
    if ($checkAuthenticated) {
      return redirect()->intended(route('admin.users.index'));
    }
    return view('auth.login.login');
  }

  public function login(LoginRequest $request): RedirectResponse
  {
    $credentials = [
      'email' => (string) $request->input('email'),
      'password' => (string) $request->input('password'),
    ];
    $remember = $request->boolean('remember');

    // Fetch user to enforce status/tenant checks
    /** @var User|null $user */
    $user = User::query()->where('email', $credentials['email'])->first();

    // Uniform error message (no filtración de estado ni existencia)
    $fail = function () use ($request) {
      return back()
        ->withInput($request->only('email', 'remember'))
        ->withErrors(['email' => 'Invalid credentials or account not allowed.']);
    };

    if (! $user) {
      return $fail();
    }

    // Status checks (block suspended / locked / pending_invite)
    if (in_array($user->status, ['suspended', 'locked', 'pending_invite'], true)) {
      return $fail();
    }

    // Enforce email verification if your users table has it
    if (\Schema::hasColumn('users', 'email_verified_at') && is_null($user->email_verified_at)) {
      return back()->withErrors(['email' => 'Please verify your email before logging in.']);
    }

    // Verify password first to avoid hitting throttling unnecessarily
    if (! Hash::check($credentials['password'], $user->password)) {
      return $fail();
    }

    // Attempt session login
    if (! Auth::attempt(['email' => $user->email, 'password' => $credentials['password']], $remember)) {
      return $fail();
    }

    $request->session()->regenerate();

    // Audit login success
    AuditLog::query()->create([
      'actor_id'     => $user->id,
      'action'       => 'auth.login',
      'subject_type' => User::class,
      'subject_id'   => $user->id,
      'meta'         => ['remember' => $remember],
    ]);

    // Redirect by role
    $u = auth()->user();
    if ($u->isPlatformSuperAdmin() || $u->hasAnyRole(['tenant_owner','tenant_admin'])) {
      return redirect()->intended(route('admin.users.index'));
    }

    return redirect()->route('account.show');
  }

  public function logout(): RedirectResponse
  {
    if (Auth::check()) {
      $uid = Auth::id();
      Auth::logout();
      request()->session()->invalidate();
      request()->session()->regenerateToken();

      // Audit logout
      AuditLog::query()->create([
        'actor_id'     => $uid,
        'action'       => 'auth.logout',
        'subject_type' => User::class,
        'subject_id'   => $uid,
        'meta'         => null,
      ]);
    }

    return redirect()->route('login')->with('success', 'You have been logged out.');
  }
}
