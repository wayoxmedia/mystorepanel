<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRolesRequest extends FormRequest
{
  public function authorize(): bool
  {
    return auth()->check(); // Gate en el controller decide el resto
  }

  public function rules(): array
  {
    return [
      'role_slugs'   => ['array'],
      'role_slugs.*' => ['string', 'exists:roles,slug'],
    ];
  }
}
