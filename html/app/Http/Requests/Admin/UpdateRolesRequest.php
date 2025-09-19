<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validation for updating a user's roles.
 *
 * Ensures that only valid tenant roles are assigned,
 * with input normalization and custom error messages.
 */
class UpdateRolesRequest extends FormRequest
{
  /**
   * Authorization is handled in the controller via $this->authorize(...).
   * @return boolean
   */
  public function authorize(): bool
  {
    return auth()->check();
  }

  /**
   * Normalize input before validation (trim/lowercase).
   * @return void
   */
  protected function prepareForValidation(): void
  {
    $role = $this->input('role');
    if (is_string($role)) {
      $this->merge(['role' => strtolower(trim($role))]);
    }
  }

  /**
   * Accept exactly one tenant role (no platform_admin here).
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    return [
      'role' => [
        'required',
        'string',
        'max:64',
        Rule::in([
          'tenant_owner',
          'tenant_admin',
          'tenant_editor',
          'tenant_viewer'
        ]),
      ],
    ];
  }

  public function messages(): array
  {
    return [
      'role.required' => 'Role is required.',
      'role.in'       => 'Role is not allowed.',
    ];
  }

  /**
   * Convenience: returns the validated role slug.
   */
  public function roleSlug(): string
  {
    return (string) $this->validated('role');
  }
}
