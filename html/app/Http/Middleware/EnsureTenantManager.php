<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantManager
{
  public function handle(Request $request, Closure $next): Response
  {
    /** @var User|null $u */
    $u = $request->user();

    // Platform SA goes everywhere
    if ($u && $u->isPlatformSuperAdmin()) {
      return $next($request);
    }

    // Tenant Owner/Admin can access all tenant resources
    if ($u && $u->hasAnyRole(['tenant_owner','tenant_admin'])) {
      return $next($request);
    }

    // Editor/Viewer: only his account management
    return redirect()
      ->route('account.show')
      ->with('error', 'Limited access. You can only manage your account.');
  }
}
