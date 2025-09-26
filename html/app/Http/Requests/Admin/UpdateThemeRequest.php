<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesNullableStrings;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateThemeRequest
 *
 * Purpose:
 * Validate and authorize Theme updates. Slug is immutable in this request.
 *
 * Notes:
 * - If you want to allow slug changes later, create a separate ability (e.g. 'updateSlug').
 */
class UpdateThemeRequest extends FormRequest
{
  use NormalizesNullableStrings;

  public function authorize(): bool
  {
    /** @var Theme|null $theme */
    $theme = $this->route('theme');
    /** @var User|null $user */
    $user = $this->user();

    return $theme instanceof Theme
      && $user !== null
      && $user->can('update', $theme);
  }

  public function rules(): array
  {
    /** @var Theme|null $theme */
    $theme = $this->route('theme');

    return [
      'tenant_id'   => [
        'required',
        'integer',
        Rule::exists('tenants', 'id')
      ],
      'name'        => ['required', 'string', 'max:191'],
      // keep slug present but immutable (same value)
      'slug'        => [
        'required',
        'string',
        'alpha_dash',
        'max:191',
        Rule::unique('themes', 'slug')
          ->ignore($theme?->id)
          ->where('tenant_id', (string) $this->input('tenant_id')),
      ],
      'status'      => ['required', Rule::in(['active', 'draft', 'archived'])],
      'description' => ['nullable', 'string'],
      'config'      => ['nullable', 'array'],
    ];
  }

  public function withValidator($validator): void
  {
    /** @var Theme|null $theme */
    $theme = $this->route('theme');

    if (! $theme instanceof Theme) {
      return;
    }

    $validator->after(function ($v) use ($theme): void {
      if ($this->filled('slug') && $this->input('slug') !== $theme->slug) {
        $v->errors()->add('slug', 'The slug cannot be changed once the theme is created.');
      }
      if ($this->filled('tenant_id')
        && (int) $this->input('tenant_id') !== $theme->tenant_id
      ) {
        $v->errors()->add('tenant_id', 'The tenant cannot be changed for an existing theme.');
      }
    });
  }

  protected function nullableStringFields(): array
  {
    return ['description'];
  }
}
