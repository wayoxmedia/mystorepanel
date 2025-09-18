<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
{
  public function authorize(): bool
  {
    return auth()->check(); // El controller hará la autorización fina
  }

  protected function prepareForValidation(): void
  {
    $status = $this->input('status');
    if (is_string($status)) {
      $this->merge(['status' => strtolower(trim($status))]);
    }
  }

  public function rules(): array
  {
    return [
      'status' => [
        'required',
        'string',
        'max:32',
        Rule::in(['active', 'locked', 'suspended']),
      ],
    ];
  }

  public function messages(): array
  {
    return [
      'status.required' => 'Status is required.',
      'status.in'       => 'Status is not allowed.',
    ];
  }

  public function statusValue(): string
  {
    return (string) $this->validated('status');
  }
}
