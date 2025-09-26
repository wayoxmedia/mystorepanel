<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesNullableStrings;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreTenantRequest
 *
 * Purpose:
 * Validate and authorize incoming requests to create a new Tenant from the admin panel.
 * Ensures data consistency for core attributes such as slug uniqueness, status transitions,
 * and seat limits. This request delegates permission checks to TenantPolicy::create().
 *
 * Assumptions:
 * - A TenantPolicy is defined with a 'create' ability.
 * - Table 'tenants' contains the following columns (nullable where indicated):
 *   name, slug (unique), status, template_id (nullable), user_seat_limit, billing_email (nullable),
 *   timezone (nullable), locale (nullable), plan (nullable), trial_ends_at (nullable), primary_domain (nullable).
 * - Table 'templates' exists and 'templates.id' is a valid foreign key for 'tenants.template_id'.
 *
 * Notes:
 * - Domain validation is intentionally permissive using a simple regex; feel free to harden it later
 *   (e.g., using Symfony Validator, custom Rule, or strict PSL validation) depending on product needs.
 * - The 'timezone' rule leverages Laravel's built-in 'timezone' validator with the 'all' set.
 * - 'locale' constraint is a pragmatic regex for values like 'en', 'es', 'en_US'; adjust as needed.
 */
class StoreTenantRequest extends FormRequest
{
  use NormalizesNullableStrings;

  /**
   * Determine if the user is authorized to make this request.
   *
   * @return bool
   */
  public function authorize(): bool
  {
    /** @var User|null $user */
    $user = $this->user();

    return $user !== null
      && $user->can('create', Tenant::class);
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    return [
      'name' => ['required', 'string', 'max:191'],

      'slug' => [
        'required',
        'string',
        'alpha_dash',
        'max:191',
        Rule::unique('tenants', 'slug'),
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
        // e.g. "en", "es", "en_US", "pt-BR"
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
        // basic host pattern like foo.example.com (no scheme/path)
        'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
      ],
    ];
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
