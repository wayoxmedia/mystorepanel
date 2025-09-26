<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TenantResource
 *
 * Purpose:
 * Normalize the JSON representation of a Tenant for API responses.
 *
 * Assumptions:
 * - Tenant model has typical fields used below.
 * - When wrapping a paginator (via collection()), Laravel will add "links" and "meta".
 */
class TenantResource extends JsonResource
{
  /**
   * Transform the resource into an array.
   *
   * @param  Request  $request
   * @return array<string, mixed>
   */
  public function toArray($request): array
  {
    return [
      'id'             => $this->id,
      'name'           => $this->name,
      'slug'           => $this->slug,
      'status'         => $this->status,
      'template_id'    => $this->template_id,
      'user_seat_limit'=> $this->user_seat_limit,
      'billing_email'  => $this->billing_email,
      'timezone'       => $this->timezone,
      'locale'         => $this->locale,
      'plan'           => $this->plan,
      'trial_ends_at'  => optional($this->trial_ends_at)?->toISOString(),
      'primary_domain' => $this->primary_domain,
      'created_at'     => optional($this->created_at)?->toISOString(),
      'updated_at'     => optional($this->updated_at)?->toISOString(),
      'deleted_at'     => optional($this->deleted_at)?->toISOString(),
    ];
  }
}
