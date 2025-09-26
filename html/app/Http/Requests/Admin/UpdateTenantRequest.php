<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesNullableStrings;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * UpdateTenantRequest
 *
 * Purpose:
 * Validate and authorize updates to an existing Tenant from the admin panel.
 * It enforces business rules including:
 *  - Immutable slug (cannot be changed via this request).
 *  - Seat-limit safety: user_seat_limit cannot be set below current active users.
 *  - Consistent validation for optional attributes (domain, timezone, locale, etc.).
 *
 * Assumptions:
 * - Route model binding provides the Tenant instance as 'tenant' route parameter.
 * - TenantPolicy defines 'update' for authorization checks.
 * - Users table has a 'status' column that uses 'active' for active users.
 * - Templates table exists and 'template_id' is a valid FK (nullable).
 *
 * Notes:
 * - If you want to allow slug changes for platform super-admins, introduce a separate
 *   policy ability (e.g., 'updateSlug') and relax the validator accordingly.
 * - Domain validation is intentionally pragmatic; replace with stricter validation as needed.
 */
class UpdateTenantRequest extends FormRequest
{
  use NormalizesNullableStrings;

  /**
   * Determine if the user is authorized to make this request.
   *
   * @return bool
   */
  public function authorize(): bool
  {
    /** @var Tenant|null $tenant */
    $tenant = $this->route('tenant');

    /** @var User|null $user */
    $user = $this->user();

    return $tenant instanceof Tenant
      && $user !== null
      && $user->can('update', $tenant);
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    /** @var Tenant|null $tenant */
    $tenant = $this->route('tenant');

    $tenantId = $tenant?->id ?? null;

    return [
      'name' => ['required', 'string', 'max:191'],

      // Slug is immutable in this request; we still keep the unique rule (with ignore)
      // to provide a clear message if the client sends a different slug.
      'slug' => [
        'required',
        'string',
        'alpha_dash',
        'max:191',
        Rule::unique('tenants', 'slug')->ignore($tenantId),
      ],

      'status' => [
        'required',
        Rule::in(['active', 'suspended', 'pending']),
      ],

      'template_id' => [
        'nullable',
        'integer',
        Rule::exists('templates', 'id'),
      ],

      'user_seat_limit' => [
        'required',
        'integer',
        'min:1',
      ],

      'billing_email' => [
        'nullable',
        'email:rfc,dns',
        'max:191',
      ],

      'timezone' => [
        'nullable',
        'timezone:all',
      ],

      'locale' => [
        'nullable',
        'string',
        'max:10',
        'regex:/^[a-z]{2}([_-][A-Za-z]{2})?$/',
      ],

      'plan' => [
        'nullable',
        'string',
        'max:100',
      ],

      'trial_ends_at' => [
        'nullable',
        'date',
      ],

      'primary_domain' => [
        'nullable',
        'string',
        'max:191',
        'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
      ],
    ];
  }

  /**
   * Configure the validator instance to enforce:
   *  - Immutable slug (cannot differ from current).
   *  - Seat limit cannot go below current active users.
   *
   * @param  Validator  $validator
   * @return void
   */
  public function withValidator(Validator $validator): void
  {
    /** @var Tenant|null $tenant */
    $tenant = $this->route('tenant');

    if (! $tenant instanceof Tenant) {
      return;
    }

    $validator->after(function ($validator) use ($tenant): void {
      // 1) Enforce immutable slug
      if ($this->filled('slug') && $this->input('slug') !== $tenant->slug) {
        // If in the future you add a policy ability 'updateSlug',
        // you could allow it for privileged users here.
        $validator->errors()->add(
          'slug',
          'The slug cannot be changed once the tenant is created.'
        );
      }

      // 2) Seat-limit safety
      if ($this->filled('user_seat_limit')) {
        $newLimit = (int) $this->input('user_seat_limit');

        // Count active users in this tenant
        $activeUsers = User::query()
          ->where('tenant_id', $tenant->id)
          ->where('status', 'active')
          ->count();

        if ($newLimit < $activeUsers) {
          $validator->errors()->add(
            'user_seat_limit',
            "The seat limit cannot be lower than the current number of active users ({$activeUsers})."
          );
        }
      }
    });
  }

  /**
   * Get custom error messages for validator errors.
   *
   * @return array<string, string>
   */
  public function messages(): array
  {
    return [
      'slug.alpha_dash' => 'The slug may only contain letters, numbers, dashes and underscores.',
      'status.in' => 'Status must be one of: active, suspended, pending.',
      'primary_domain.regex' => 'Primary domain must be a valid hostname (no scheme or path).',
      'locale.regex' => 'Locale must look like "en", "es", "en_US" or "pt-BR".',
    ];
  }

  /**
   * @inheritDoc
   */
  protected function nullableStringFields(): array
  {
    return ['billing_email', 'timezone', 'locale', 'plan', 'primary_domain'];
  }
}
