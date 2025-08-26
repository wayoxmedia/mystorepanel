<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
    $mode = (string) $this->input('mode');

    $rules = [
      'role_slug' => ['required', 'string', 'exists:roles,slug'],
      'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
      'mode'      => ['required', Rule::in(['create', 'invite'])],
      'email'     => ['required', 'email', 'max:255'],
    ];

    if ($mode === 'create') {
      $rules = array_merge($rules, [
        'name'                  => ['required', 'string', 'max:50'],
        'password'              => ['required', 'string', 'min:10'],
        // 'password_confirmation' implicit by 'confirmed', can be added if needed
      ]);
    }

    return $rules;
  }

  /**
   * @param $validator
   * @return void
   */
  public function withValidator($validator): void
  {
    $validator->after(function ($validator) {
      $actor = $this->user();
      if (! $actor) {
        return;
      }

      $roleSlug = (string) $this->input('role_slug');
      /** @var Role|null $role */
      $role = Role::query()->where('slug', $roleSlug)->first();
      if (! $role) {
        return; // lo captura la regla exists
      }

      $tenantId = $this->input('tenant_id');
      $isSA = $actor->isPlatformSuperAdmin();

      if ($isSA) {
        // SA puede crear usuarios de plataforma (tenant_id debe ser null)
        if ($role->scope === 'platform' || $role->slug === 'platform_super_admin') {
          if (! is_null($tenantId) && $tenantId !== '') {
            $validator->errors()->add('tenant_id', 'Tenant must be empty when assigning a platform role.');
          }
        } else {
          // Para roles de tenant, EXIGIR tenant_id
          if (is_null($tenantId) || $tenantId === '') {
            $validator->errors()->add('tenant_id', 'Tenant is required for non-platform roles.');
          }
        }
      } else {
        // No-SA: solo su tenant y nunca roles de plataforma
        if ($role->scope === 'platform' || $role->slug === 'platform_super_admin') {
          $validator->errors()->add('role_slug', 'You are not allowed to assign platform roles.');
        }

        if (is_null($tenantId) || $tenantId === '') {
          $validator->errors()->add('tenant_id', 'Tenant is required.');
        } elseif ((int) $tenantId !== (int) $actor->tenant_id) {
          $validator->errors()->add('tenant_id', 'You can only manage users in your tenant.');
        }
      }
    });
  }

  /**
   * @return array
   */
  public function attributes(): array
  {
    return [
      'role_slug' => 'role',
      'tenant_id' => 'tenant',
    ];
  }

  /**
   * Prepare the data for validation.
   *
   * Autofill tenant_id for non-SA
   * @return void
   */
  protected function prepareForValidation(): void
  {
    $actor = $this->user();
    if ($actor && !$actor->isPlatformSuperAdmin()) {
      // Autorrellenar tenant_id para no-SA
      $this->merge(['tenant_id' => $actor->tenant_id]);
    }
  }

}
