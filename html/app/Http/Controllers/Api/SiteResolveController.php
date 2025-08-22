<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Site;
use App\Models\ThemeSetting;
use App\Http\Resources\SiteResolveResource;
use Throwable;

/**
 * Class SiteResolveController
 *
 * Handles resolving a site by its domain, returning site, tenant, template, and settings.
 */
class SiteResolveController extends Controller
{
  /**
   * GET /api/sites/resolve?domain=template1.test
   * Resolves a site by domain and returns: site, tenant, template, settings.
   *
   * @param  Request  $request
   * @return SiteResolveResource|JsonResponse
   * @throws Exception
   * @throws Throwable
   */
  public function resolveByDomain(Request $request): SiteResolveResource|JsonResponse
  {
    $request->validate([
      'domain' => ['required', 'string', 'max:255'],
    ]);

    $domain = $this->normalizeDomain($request->query('domain'));

    // Cache TTL (seconds). Falls back to env if config not set.
    $ttl = (int)config('cache.ttl.resolve', (int)env('RESOLVE_CACHE_TTL', 600));
    $cacheKey = "resolve:site:{$domain}";

    $payload = Cache::remember($cacheKey, $ttl, function () use ($domain) {
      $site = Site::query()
        ->with(['tenant', 'template'])
        ->where('domain', $domain)
        ->first();

      if (!$site) {
        return null;
      }

      // Merge theme settings for (tenant, template)
      $settings = ThemeSetting::query()
        ->where('tenant_id', $site->tenant_id)
        ->where('template_id', $site->template_id)
        ->get()
        ->mapWithKeys(fn($row) => [$row->key => $row->value])
        ->toArray();

      return [
        'site' => $site,
        'tenant' => $site->tenant,
        'template' => $site->template,
        'settings' => $settings,
      ];
    });

    if (!$payload) {
      return response()->json(['message' => 'Site not found'], 404);
    }

    return SiteResolveResource::make($payload);
  }

  /**
   * Normalize incoming domains (strip scheme, www, and trailing slash).
   *
   * @param  string  $domain
   * @return string
   */
  protected function normalizeDomain(string $domain): string
  {
    $domain = strtolower(trim($domain));
    $domain = preg_replace('/^https?:\/\//', '', $domain);
    $domain = preg_replace('/^www\./', '', $domain);
    return rtrim($domain, '/');
  }
}
