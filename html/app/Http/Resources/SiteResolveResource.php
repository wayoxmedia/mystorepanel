<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the resolved site payload: site, tenant, template, settings.
 */
class SiteResolveResource extends JsonResource
{
  /**
   * Transform the resource into an array.
   *  [
   *    'site' => \App\Models\Site,
   *    'tenant' => \App\Models\Tenant,
   *    'template' => \App\Models\Template,
   *    'settings' => array
   *  ]
   * @param Request $request Structure of the request data.
   *
   * @return array
   */
  public function toArray(Request $request): array
  {
    $site = $this->resource['site'];
    $tenant = $this->resource['tenant'];
    $template = $this->resource['template'];
    $settings = $this->resource['settings'] ?? [];

    return [
      'site' => [
        'id' => $site->id,
        'domain' => $site->domain,
        'meta' => $site->meta,
      ],
      'tenant' => [
        'id' => $tenant->id,
        'name' => $tenant->name,
        'slug' => $tenant->slug,
        'email' => $tenant->email,
        'phone' => $tenant->phone,
      ],
      'template' => [
        'id' => $template->id,
        'slug' => $template->slug,
        'name' => $template->name,
      ],
      'settings' => $settings,
    ];
  }
}
