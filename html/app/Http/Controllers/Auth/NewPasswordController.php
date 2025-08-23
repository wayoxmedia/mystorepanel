<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\AuditLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
  public function create(string $token): View {
    return view('auth.passwords.reset', [
      'token' => $token,
      'email' => request('email'),
    ]);
  }

  public function store(ResetPasswordRequest $request): RedirectResponse
  {
    $status = Password::reset(
      $request->only('email','password','password_confirmation','token'),
      function ($user, $password) {
        $user->password = Hash::make($password);
        $user->setRememberToken(Str::random(60));
        $user->save();
        event(new PasswordReset($user));

        AuditLog::create([
          'actor_id' => $user->id,
          'action' => 'auth.password_reset',
          'subject_type' => get_class($user),
          'subject_id' => $user->id,
          'meta' => null,
        ]);
      }
    );

    return $status === Password::PASSWORD_RESET
      ? redirect()->route('login')->with('success', __($status))
      : back()->withErrors(['email' => [__($status)]]);
  }
}
