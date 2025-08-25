<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantManager
{
  public function handle(Request $request, Closure $next): Response
  {
    $u = $request->user();

    // Platform SA entra en todo
    if ($u && method_exists($u, 'isPlatformSuperAdmin') && $u->isPlatformSuperAdmin()) {
      return $next($request);
    }

    // Tenant Owner/Admin pueden entrar al backend
    if ($u && method_exists($u, 'hasAnyRole') && $u->hasAnyRole(['tenant_owner','tenant_admin'])) {
      return $next($request);
    }

    // Editor/Viewer: solo su cuenta
    return redirect()->route('account.show')->with('error', 'Limited access. You can only manage your account.');
  }
}
