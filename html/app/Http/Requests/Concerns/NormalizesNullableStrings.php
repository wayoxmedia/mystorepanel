<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * NormalizesNullableStrings
 *
 * Purpose:
 * Provide a reusable prepareForValidation() implementation that converts
 * blank string inputs into null for a configurable subset of fields.
 *
 * Assumptions:
 * - Intended to be used in Laravel FormRequest subclasses.
 * - Child classes override nullableStringFields() to declare which keys to normalize.
 *
 * Notes:
 * - PSR-12 compliant. Keep trait focused on input normalization only.
 */
trait NormalizesNullableStrings
{
  /**
   * List of input keys that should be normalized to null when blank.
   *
   * @return array<int, string>
   */
  protected function nullableStringFields(): array
  {
    return [];
  }

  /**
   * Convert blank strings to null for configured keys.
   *
   * @return void
   */
  protected function normalizeNullableStrings(): void
  {
    foreach ($this->nullableStringFields() as $key) {
      if ($this->has($key)) {
        $value = $this->input($key);
        if (is_string($value) && trim($value) === '') {
          $this->merge([$key => null]);
        }
      }
    }
  }

  /**
   * Hook into the request lifecycle before validation.
   *
   * @return void
   */
  protected function prepareForValidation(): void
  {
    $this->normalizeNullableStrings();
  }
}
