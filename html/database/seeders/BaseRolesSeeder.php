<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds baseline roles used across the app.
 * Idempotent: safe to run multiple times (upsert by slug).
 */
final class BaseRolesSeeder extends Seeder
{
  public function run(): void
  {
    $now = Carbon::now();

    $roles = [
      // Platform-level
      ['name' => 'Platform Super Admin', 'slug' => 'platform_super_admin', 'scope' => 'platform'],
      // Tenant-level
      ['name' => 'Tenant Owner',         'slug' => 'tenant_owner',         'scope' => 'tenant'],
      ['name' => 'Tenant Admin',         'slug' => 'tenant_admin',         'scope' => 'tenant'],
      ['name' => 'Tenant Editor',        'slug' => 'tenant_editor',        'scope' => 'tenant'],
      ['name' => 'Tenant Viewer',        'slug' => 'tenant_viewer',        'scope' => 'tenant'],
    ];

    $payload = array_map(static function (array $r) use ($now) {
      $r['created_at'] = $now;
      $r['updated_at'] = $now;
      return $r;
    }, $roles);

    DB::table('roles')->upsert(
      $payload,
      ['slug'],                     // unique key
      ['name', 'scope', 'updated_at'] // columns to update on conflict
    );
  }
}
