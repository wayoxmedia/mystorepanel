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
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class InvitationAcceptanceController extends Controller
{
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
      $inv->update(['status' => 'accepted']);
      return redirect('/')
        ->with('error', 'Email already in use. If this is you, try logging in.');
    }

    // Create the user
    $user = new User();
    $user->name              = (string) $request->input('name');
    $user->email             = $inv->email;
    $user->tenant_id         = $inv->tenant_id;     // null for platform staff invites
    $user->status            = 'active';
    $user->password          = Hash::make((string) $request->input('password'));
    // If your users table has email_verified_at (typical), we confirm here:
    if ($user->isFillable('email_verified_at') || \Schema::hasColumn('users', 'email_verified_at')) {
      $user->email_verified_at = now();
    }
    $user->save();

    // Attach role if present
    if ($inv->role_id) {
      $user->roles()->syncWithoutDetaching([$inv->role_id]);
    }

    // Close invitation
    $inv->update(['status' => 'accepted']);

    // Audit
    AuditLog::query()->create([
      'actor_id'     => $user->id, // self-action
      'action'       => 'invite.accepted',
      'subject_type' => Invitation::class,
      'subject_id'   => $inv->id,
      'meta'         => [
        'email'     => $inv->email,
        'tenant_id' => $inv->tenant_id,
        'role_id'   => $inv->role_id,
      ],
    ]);

    // Optional: auto-login once we tengamos login listo.
    // Auth::login($user);

    return redirect('/')
      ->with('success', 'Your account is ready. You can now log in.');
  }
}
