<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserStatusRequest extends FormRequest
{
  public function authorize(): bool
  {
    return auth()->check(); // El controller hará la autorización fina
  }

  public function rules(): array
  {
    return [
      'status' => ['required', 'in:active,suspended,locked'],
      'reason' => ['nullable', 'string', 'max:500'],
    ];
  }
}
