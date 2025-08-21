<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the resolved site payload: site, tenant, template, settings.
 */
class SiteResolveResource extends JsonResource
{
  /**
   * @param  array  $request  Structure:
   *  [
   *    'site' => \App\Models\Site,
   *    'tenant' => \App\Models\Tenant,
   *    'template' => \App\Models\Template,
   *    'settings' => array
   *  ]
   */
  public function toArray($request): array
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
