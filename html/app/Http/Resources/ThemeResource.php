<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ThemeResource
 *
 * Purpose:
 * Normalize Theme JSON responses. When returning a paginator (collection),
 * Laravel will include "links" and "meta".
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $deleted_at
 * @property mixed $id
 * @property mixed $tenant_id
 * @property mixed $name
 * @property mixed $slug
 * @property mixed $status
 * @property mixed $description
 * @property mixed $config
 */
class ThemeResource extends JsonResource
{
  /**
   * @param  Request  $request
   * @return array<string, mixed>
   */
  public function toArray(Request $request): array
  {
    return [
      'id'          => $this->id,
      'tenant_id'   => $this->tenant_id,
      'name'        => $this->name,
      'slug'        => $this->slug,
      'status'      => $this->status,
      'description' => $this->description,
      'config'      => $this->config,
      'created_at'  => optional($this->created_at)?->toISOString(),
      'updated_at'  => optional($this->updated_at)?->toISOString(),
      'deleted_at'  => optional($this->deleted_at)?->toISOString(),
    ];
  }
}
