<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Throwable;

/**
 * Class InvitationAcceptanceController
 *
 * Handles the acceptance of user invitations.
 */
class InvitationAcceptanceController extends Controller
{
  /**
   * Display the invitation acceptance view.
   *
   * @param  string  $token
   * @return View|RedirectResponse
   */
  public function show(string $token): View|RedirectResponse
  {
    $inv = Invitation::query()
      ->where('token', $token)
      ->where('status', 'pending')
      ->where(function ($q) {
        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
      })
      ->first();

    if (! $inv) {
      return redirect('/')
        ->with('error', 'Invalid or expired invitation.');
    }

    $tenant = $inv->tenant_id ? Tenant::query()->find($inv->tenant_id) : null;
    $role   = $inv->role_id ? Role::query()->find($inv->role_id) : null;

    return view('auth.invitations.accept', [
      'inv'    => $inv,
      'tenant' => $tenant,
      'role'   => $role,
      'token'  => $token,
    ]);
  }

  /**
   * Handle an incoming registration request.
   *
   * @param  AcceptInvitationRequest  $request
   * @return RedirectResponse
   */
  public function store(AcceptInvitationRequest $request): RedirectResponse
  {
    $token = (string) $request->input('token');

    $inv = Invitation::query()
      ->where('token', $token)
      ->where('status', 'pending')
      ->where(function ($q) {
        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
      })
      ->first();

    if (! $inv) {
      return redirect('/')
        ->with('error', 'Invalid or expired invitation.');
    }

    // Ensure the email is still free
    if (User::query()->where('email', $inv->email)->exists()) {
      // Mark invitation as accepted anyway to avoid reuse
      $inv->update(['status' => 'accepted', 'expires_at' => now()]);
      return redirect('/')
        ->with('error', 'Email already in use. If this is you, try logging in.');
    }

    // If platform staff invitation (no tenant), skip seat check.
    // This may be removed in the future.
    if (is_null($inv->tenant_id)) {
      return $this->acceptWithoutSeatCheck($request, $inv);
    }

    try {
      return DB::transaction(function () use ($request, $inv) {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->whereKey($inv->tenant_id)->lockForUpdate()->firstOrFail();

        // Reread invitation with lock to ensure it's still valid/pending
        /** @var Invitation $freshInv */
        $freshInv = Invitation::query()->whereKey($inv->id)->lockForUpdate()->firstOrFail();
        if ($freshInv->status !== 'pending' || ($freshInv->expires_at && $freshInv->expires_at->isPast())) {
          return redirect('/')->with('error', 'Invitation is no longer valid.');
        }

        // Count actual seats used
        $used = User::query()
          ->where('tenant_id', $tenant->id)
          ->whereIn('status', ['active','locked','suspended'])
          ->count();

        $limit = $tenant->seatsLimit();

        // Hard rule:if no seats available (used >= limit), don't allow acceptance.
        if ($used >= $limit) {
          // Leave invitation pending (still "reserving" seat),
          // but block acceptance until seats are freed.
          return redirect('/')
            ->with('error', 'No seats available for this tenant. Please contact your administrator.');
        }

        // Email still libre
        $email = (string) $freshInv->email;
        if (User::query()->where('email', $email)->exists()) {
          // Mark as accepted anyway to avoid reuse; the user already exists (edge case).
          $freshInv->update(['status' => 'accepted', 'expires_at' => now()]);
          return redirect('/')->with('error', 'Email already in use. Try logging in.');
        }

        // Create user within transaction
        $user = new User();
        $user->name      = (string) $request->input('name');
        $user->email     = $email;
        $user->tenant_id = $tenant->id;
        $user->status    = 'active';
        $user->password  = Hash::make((string) $request->input('password'));
        $user->save();

        // Attach role (if any)
        if ($freshInv->role_id) {
          $user->roles()->syncWithoutDetaching([$freshInv->role_id]);
        }

        // Close invitation
        $freshInv->update([
          'status'     => 'accepted',
          'expires_at' => now(),
        ]);

        // Audit
        AuditLog::query()->create([
          'actor_id'     => $user->id, // self-action
          'action'       => 'invite.accepted',
          'subject_type' => Invitation::class,
          'subject_id'   => $freshInv->id,
          'meta'         => [
            'email'     => $email,
            'tenant_id' => $tenant->id,
            'role_id'   => $freshInv->role_id,
          ],
        ]);

        // Auto-login and email verification
        Auth::guard('web')->login($user);
        request()->session()->regenerate();
        if (! $user->hasVerifiedEmail()) {
          $user->sendEmailVerificationNotification();
        }

        return redirect()->route('verification.notice')
          ->with('success', 'Account created. Please verify your email.');
      });
    } catch (Throwable $e) {
      return back()->withErrors(['accept' => $e->getMessage()]);
    }
  }

  /** ------ Helpers ------ */

  /**
   * Accept invitation without seat check (for platform staff invitations).
   *
   * May be removed in the future.
   * @param  AcceptInvitationRequest  $request
   * @param  Invitation               $inv
   * @return RedirectResponse
   */
  private function acceptWithoutSeatCheck(AcceptInvitationRequest $request, Invitation $inv): RedirectResponse
  {
    // Si ya está aceptada/caducada
    if ($inv->status !== 'pending' || ($inv->expires_at && $inv->expires_at->isPast())) {
      return redirect('/')->with('error', 'Invitation is no longer valid.');
    }

    // Email available?
    if (User::query()->where('email', $inv->email)->exists()) {
      $inv->update(['status' => 'accepted', 'expires_at' => now()]);
      return redirect('/')->with('error', 'Email already in use. Try logging in.');
    }

    $user = new User();
    $user->name      = (string) $request->input('name');
    $user->email     = $inv->email;
    $user->tenant_id = null; // staff de plataforma
    $user->status    = 'active';
    $user->password  = Hash::make((string) $request->input('password'));
    $user->save();

    if ($inv->role_id) {
      $user->roles()->syncWithoutDetaching([$inv->role_id]);
    }

    $inv->update(['status' => 'accepted', 'expires_at' => now()]);

    AuditLog::query()->create([
      'actor_id'     => $user->id,
      'action'       => 'invite.accepted',
      'subject_type' => Invitation::class,
      'subject_id'   => $inv->id,
      'meta'         => ['email' => $inv->email, 'tenant_id' => null, 'role_id' => $inv->role_id],
    ]);

    Auth::guard('web')->login($user);
    request()->session()->regenerate();
    if (method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
      $user->sendEmailVerificationNotification();
    }

    return redirect()->route('verification.notice')
      ->with('success', 'Account created. Please verify your email.');
  }
}
