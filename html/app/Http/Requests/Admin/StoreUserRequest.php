<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request for storing a new user.
 * Handles both platform and tenant user creation.
 * Supports both direct creation and invitation workflows.
 */
class StoreUserRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   * @return bool
   */
  public function authorize(): bool
  {
    return auth()->check();
  }

  /**
   * Get the validation rules that apply to the request.
   * @return array[]
   */
  public function rules(): array
  {
    // If platform admin: tenant_id can be nullable to create staff.
    return [
      'name'      => ['required', 'string', 'max:255'],
      'email'     => ['required', 'email', 'max:255', 'unique:users,email'],
      'tenant_id' => ['nullable', 'exists:tenants,id'],
      'role_slug' => ['required', 'in:platform_super_admin,tenant_owner,tenant_admin,tenant_editor,tenant_viewer'],
      'mode'      => ['required', 'in:create,invite'],
      'password'  => ['required_if:mode,create', 'nullable', 'string', 'min:10'],
    ];
  }
}
