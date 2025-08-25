<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
  public function authorize(): bool { return auth()->check(); }

  public function rules(): array
  {
    return [
      'email'   => ['required', 'email', 'max:255'],
      'role_id' => ['required', 'exists:roles,id'],
    ];
  }
}
