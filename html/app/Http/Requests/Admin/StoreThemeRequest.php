<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesNullableStrings;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreThemeRequest
 *
 * Purpose:
 * Validate and authorize creation of a per-tenant Theme with per-tenant slug uniqueness.
 *
 * Notes:
 * - Slug uniqueness is enforced within the same tenant_id.
 * - 'config' accepts array (will be JSON-encoded by Eloquent cast).
 */
class StoreThemeRequest extends FormRequest
{
  use NormalizesNullableStrings;

  public function authorize(): bool
  {
    /** @var User|null $user */
    $user = $this->user();
    if (!$user) {
      return false;
    }

    // SA allowed
    if ($user->isPlatformSuperAdmin()) {
      return true;
    }
    // tenant_owner/admin creating only for their own tenant
    $isManager = $user->hasAnyRole(['tenant_owner', 'tenant_admin']);
    $matchesTenant = (int) $this->input('tenant_id') === (int) $user->tenant_id;

    return $isManager && $matchesTenant && ($user->status ?? 'active') === 'active';
  }

  public function rules(): array
  {
    $tenantId = (string) $this->input('tenant_id');

    return [
      'tenant_id'   => ['required', 'integer', Rule::exists('tenants', 'id')],
      'name'        => ['required', 'string', 'max:191'],
      'slug'        => [
        'required',
        'string',
        'alpha_dash',
        'max:191',
        // unique per tenant_id
        Rule::unique('themes', 'slug')->where('tenant_id', $tenantId),
      ],
      'status'      => ['required', Rule::in(['active', 'draft', 'archived'])],
      'description' => ['nullable', 'string'],
      'config'      => ['nullable', 'array'],
    ];
  }

  protected function nullableStringFields(): array
  {
    return ['description'];
  }
}
