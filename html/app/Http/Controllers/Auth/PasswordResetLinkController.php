<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Controller handling password reset link requests.
 */
class PasswordResetLinkController extends Controller
{
  /**
   * Display the password reset link request view.
   * @return View
   */
  public function create(): View
  {
    return view('auth.passwords.email');
  }

  /**
   * Handle an incoming password reset link request.
   * @param ForgotPasswordRequest $request
   * @return RedirectResponse
   */
  public function store(ForgotPasswordRequest $request): RedirectResponse
  {
    $status = Password::sendResetLink($request->only('email'));
    return $status === Password::RESET_LINK_SENT
      ? back()->with('success', __($status))
      : back()->withErrors(['email' => __($status)]);
  }
}
