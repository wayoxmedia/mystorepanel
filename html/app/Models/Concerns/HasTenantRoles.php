<?php

namespace App\Models\Concerns;

trait HasTenantRoles
{
  /**
   * Define hierarchy of roles if needed.
   * Higher number means higher privilege.
   */
  protected array $ROLE_HIERARCHY = [
    'tenant_viewer' => 10,
    'tenant_editor' => 20,
    'tenant_admin' => 30,
    'tenant_owner' => 40,
    'platform_super_admin' => 100,
  ];

  public function getRoleCode(): ?string
  {
    // 1) si hay relación roles, intenta slug/code/name
    if (method_exists($this, 'role')) {
      $role = $this->getRelationValue('role') ?: $this->role()->first(
        ['id', 'slug', 'code', 'name']
      );
      if ($role) {
        foreach (['slug', 'code', 'name'] as $f) {
          if (isset($role->{$f}) && is_string($role->{$f})) {
            return strtolower($role->{$f});
          }
        }
      }
    }
    // 2) fallback: mapa en config/auth.php
    $map = (array)config('roles.role_map', []);
    if (isset($this->role_id, $map[$this->role_id])) {
      return strtolower((string)$map[$this->role_id]);
    }
    return null;
  }

  public function isPlatformSuperAdmin(): bool
  {
    return $this->getRoleCode() === 'platform_super_admin';
  }

  public function hasRoleForTenant(
    int $tenantId,
    array $allowedRoles,
    bool $useHierarchy = true
  ): bool {
    $code = $this->getRoleCode();
    if (!$code) {
      return false;
    }

    // super admin always passes
    if ($code === 'platform_super_admin') {
      return true;
    }

    // usuarios tenant_* solo en su tenant
    if ((int)($this->tenant_id ?? 0) !== $tenantId) {
      return false;
    }

    $allowedRoles = array_map('strtolower', $allowedRoles);

    if (!$useHierarchy) {
      return in_array($code, $allowedRoles, true);
    }

    /**
     * Explanation of role hierarchy check:
     * | $need = min(...)
     * |-
     *
     * Take all allowed roles you put in the route
     * ($allowedRoles, e.g. ['tenant_admin','tenant_owner']).
     *
     * Will convert them to their levels (30, 40 in this example).
     * Get the minimum of those levels (here it would be 30).
     *
     * If an unknown role appears, use PHP_INT_MAX (very large)
     * so it doesn't enable by mistake.
     *
     * Idea: the minimum allowed determines the threshold.
     * If the route allows tenant_admin or higher, the threshold is 30.
     *
     * | $have
     * |-
     *
     * The level of the current user (e.g. tenant_owner = 40).
     *
     * | return $have >= $need
     * |-
     *
     * If the user level is greater or equal to the threshold, it passes.
     *
     * With this example: owner (40) ≥ admin (30) ⇒ passes.
     * An editor (20) ≥ admin (30) ⇒ fails.
     */
    $h = $this->ROLE_HIERARCHY;
    $need = min(
      array_map(
        fn($r) => $h[$r] ?? PHP_INT_MAX,
        $allowedRoles
      )
    );
    $have = $h[$code] ?? 0;

    return $have >= $need;
  }
}
