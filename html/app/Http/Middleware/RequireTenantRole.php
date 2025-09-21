<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class RequireTenantRole
{
  /**
   * Uso: ->middleware('role:tenant_owner,admin')
   */
  public function handle(Request $request, Closure $next, string ...$allowedRoles)
  {
    /** @var User $user */
    $user = $request->user();

    // 401 si no hay auth (debe ir después de 'auth:api' o similar)
    if (! $user) {
      return response()->json(
        ['message' => 'Unauthorized'],
        401
      );
    }

    // Resolver tenant_id (query, header o ruta). Ajusta si usas otro patrón.
    $tenantId = $this->resolveTenantId($request);
    if ($tenantId <= 0) {
      return response()->json(
        ['message' => 'Bad Request: tenant_id missing'],
        400
      );
    }

    // Verificar rol
    if (method_exists($user, 'hasRoleForTenant')) {
      if ($user->hasRoleForTenant($tenantId, $allowedRoles)) {
        return $next($request);
      }
    }

    return response()->json(
      ['message' => 'Forbidden: insufficient role'],
      403
    );
  }

  private function resolveTenantId(Request $request): int
  {
    // 1) query ?tenant_id=
    $q = $request->query('tenant_id');
    if (ctype_digit((string)$q) && (int)$q > 0) {
      return (int) $q;
    }

    // 2) header X-Tenant-Id
    $h = $request->header('X-Tenant-Id');
    if (ctype_digit((string)$h) && (int)$h > 0) {
      return (int) $h;
    }

    // 3) param de ruta común
    foreach (['tenant_id','tenantId','tenant'] as $key) {
      $v = $request->route($key);
      if (ctype_digit((string)$v) && (int)$v > 0) {
        return (int) $v;
      }
    }

    return 0;
  }
}
