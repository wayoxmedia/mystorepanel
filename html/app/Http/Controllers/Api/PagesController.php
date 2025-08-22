<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * PagesController
 *
 * Handles requests for tenant-specific pages.
 * This is a simplified version for demonstration purposes.
 */
class PagesController extends Controller
{
  /**
   * @param  Request  $request
   * @param  integer  $tenant
   * @return JsonResponse
   */
    public function show(Request $request, int $tenant)
    {
        // Lee slug del query y normaliza
        $slug = trim((string) $request->query('slug', 'home'));
        $slug = trim($slug, "/ \t\n\r\0\x0B");

        // TODO: aquí iría tu lookup real en BD por $tenant y $slug.
        // Por ahora devolvemos dummy para probar el flujo.
        $fixtures = [
            'home' => [
                'title'   => 'Welcome to Eglee',
                'content' => '<p>This is the home page rendered from backend data.</p>',
            ],
            'about' => [
                'title'   => 'About Eglee',
                'content' => '<p>About page content from backend.</p>',
            ],
        ];

        if (!array_key_exists($slug, $fixtures)) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        return response()->json([
            'ok'   => true,
            'data' => [
                'tenant_id' => $tenant,
                'slug'      => $slug,
                'page'      => $fixtures[$slug],
            ],
        ]);
    }
}
