<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  /**
   * Backfill role_user from the legacy users.role (string).
   *
   * Mapping:
   *   editor -> tenant_editor (default in your current schema)
   *   admin  -> tenant_admin
   *   viewer -> tenant_viewer
   *   owner  -> tenant_owner
   *   platform / superadmin / super_admin -> platform_super_admin
   *   (else) -> tenant_viewer
   *
   * The insert is idempotent via LEFT JOIN role_user guard.
   */
  public function up(): void
  {
    // Ensure roles exist (created in previous migration).
    DB::statement("
      INSERT INTO role_user (user_id, role_id, created_at, updated_at)
      SELECT u.id, r.id, NOW(), NOW()
      FROM users u
      JOIN roles r ON r.slug = (
        CASE
          WHEN u.role = 'editor' THEN 'tenant_editor'
          WHEN u.role = 'admin'  THEN 'tenant_admin'
          WHEN u.role = 'viewer' THEN 'tenant_viewer'
          WHEN u.role = 'owner'  THEN 'tenant_owner'
          WHEN u.role IN ('platform','superadmin','super_admin') THEN 'platform_super_admin'
          ELSE 'tenant_viewer'
        END
      )
      LEFT JOIN role_user ru ON ru.user_id = u.id AND ru.role_id = r.id
      WHERE ru.user_id IS NULL
    ");
  }

  public function down(): void
  {
    // Best-effort: remove any rows we inserted (based on roles set)
    DB::statement("
      DELETE ru FROM role_user ru
      JOIN roles r ON r.id = ru.role_id
      WHERE r.slug IN ('platform_super_admin','tenant_owner','tenant_admin','tenant_editor','tenant_viewer')
    ");
  }
};
