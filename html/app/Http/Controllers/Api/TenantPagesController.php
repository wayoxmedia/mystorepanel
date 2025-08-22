<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant;
use App\Models\Page;
use App\Http\Resources\PageResource;

/**
 * Class TenantPagesController
 *
 * Handles requests for tenant-specific pages.
 * Provides an API endpoint to retrieve a single page by slug for a specific tenant.
 */
class TenantPagesController extends Controller
{
  /**
   * GET /api/tenants/{tenant}/pages?slug=/about
   *
   * Returns a single page for the given tenant and slug.
   *
   * @param  Request  $request
   * @param  Tenant   $tenant
   * @return JsonResponse|JsonResource
   */
  public function show(Request $request, Tenant $tenant)
  {
    $request->validate([
      'slug' => ['required', 'string', 'max:255'],
    ]);

    $slug = $this->normalizeSlug($request->query('slug'));

    $ttl = (int)config('cache.ttl.pages', (int)env('PAGES_CACHE_TTL', 600));
    $cacheKey = "page:tenant:{$tenant->id}:slug:{$slug}";

    $page = Cache::remember($cacheKey, $ttl, function () use ($tenant, $slug) {
      return Page::query()
        ->where('tenant_id', $tenant->id)
        ->where('slug', $slug)
        ->first();
    });

    if (!$page) {
      return response()->json(['message' => 'Page not found'], 404);
    }

    return PageResource::make($page);
  }

  /**
   * Normalize slugs and treat empty as root "/".
   *
   * @param  string $slug
   * @return string
   */
  protected function normalizeSlug(string $slug): string
  {
    $slug = trim($slug);
    if ($slug === '' || $slug === '/') {
      return '/';
    }
    // Collapse repeated slashes
    $slug = preg_replace('#/+#', '/', $slug);

    // Ensure leading slash
    if ($slug[0] !== '/') {
      $slug = '/'.$slug;
    }

    // Remove trailing slash except for root
    return rtrim($slug, '/') ?: '/';
  }
}
