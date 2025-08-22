<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true; // public flow
  }

  public function rules(): array
  {
    return [
      'token'                 => ['required', 'string', 'min:10'],
      'name'                  => ['required', 'string', 'max:255'],
      'password'              => ['required', 'string', 'min:10', 'confirmed'],
      'password_confirmation' => ['required', 'string', 'min:10'],
    ];
  }
}
