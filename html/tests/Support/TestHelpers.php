<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait TestHelpers
{
  /** Resolve the User Eloquent model class from config. */
  protected function userModel(): string
  {
    return (string) (config('auth.providers.users.model') ?? User::class);
  }

  /** Find a User model instance by id using the configured model class. */
  protected function findUserModel(int $id): ?Authenticatable
  {
    $class = $this->userModel();
    /** @var Model|null $model */
    $model = $class::query()->find($id);
    return $model instanceof Authenticatable ? $model : null;
  }

  /**
   * Create a tenant filling only existing columns.
   *
   * Returns tenant id.
   */
  protected function seedTenant(array $overrides = []): int
  {
    $name = $overrides['name'] ?? 'Test Tenant';
    $data = [
      'created_at' => now(),
      'updated_at' => now(),
    ];

    // Common tenant columns (only if present)
    if (Schema::hasColumn('tenants', 'name')) {
      $data['name'] = $name;
    }
    if (Schema::hasColumn('tenants', 'slug')) {
      $data['slug'] = Str::slug($name) . '-' . Str::lower(Str::random(6));
    }
    if (Schema::hasColumn('tenants', 'status')) {
      $data['status'] = $overrides['status'] ?? 'active';
    }
    if (Schema::hasColumn('tenants', 'user_seat_limit')) {
      $data['user_seat_limit'] = $overrides['user_seat_limit'] ?? 5;
    }
    if (Schema::hasColumn('tenants', 'plan')) {
      $data['plan'] = $overrides['plan'] ?? 'free';
    }
    if (Schema::hasColumn('tenants', 'email')) {
      $data['email'] = $overrides['email'] ?? 'owner@example.test';
    }

    $data = array_merge($data, $overrides);

    return DB::table('tenants')->insertGetId($data);
  }

  /**
   * Create an invitation for a tenant.
   *
   * Returns ['id' => int, 'token' => string].
   */
  protected function seedInvitation(int $tenantId, array $overrides = []): array
  {
    $token = $overrides['token'] ?? Str::random(40);
    $data = [
      'tenant_id'  => $tenantId,
      'created_at' => now(),
      'updated_at' => now(),
    ];

    if (Schema::hasColumn('invitations', 'email')) {
      $data['email'] = $overrides['email'] ?? 'invitee@example.test';
    }
    if (Schema::hasColumn('invitations', 'token')) {
      $data['token'] = $token;
    }
    if (Schema::hasColumn('invitations', 'status')) {
      $data['status'] = $overrides['status'] ?? 'pending';
    }
    if (Schema::hasColumn('invitations', 'expires_at')) {
      $data['expires_at'] = $overrides['expires_at'] ?? now()->addDay();
    }
    if (Schema::hasColumn('invitations', 'role')) {
      $data['role'] = $overrides['role'] ?? 'member';
    }
    if (Schema::hasColumn('invitations', 'invited_by')) {
      $data['invited_by'] = $overrides['invited_by'] ?? null;
    }

    $data = array_merge($data, $overrides);

    $id = DB::table('invitations')->insertGetId($data);

    return ['id' => $id, 'token' => $data['token'] ?? $token];
  }

  /**
   * Create a basic user. Returns ['id' => int, 'email' => string].
   *
   * Detects common columns to avoid schema assumptions.
   */
  protected function seedUser(array $overrides = []): array
  {
    $email = $overrides['email'] ?? ('user_' . Str::lower(Str::random(6)) . '@example.test');
    $data = [
      'created_at' => now(),
      'updated_at' => now(),
    ];

    if (Schema::hasColumn('users', 'name')) {
      $data['name'] = $overrides['name'] ?? 'Test User';
    }
    if (Schema::hasColumn('users', 'email')) {
      $data['email'] = $email;
    }
    if (Schema::hasColumn('users', 'password')) {
      $data['password'] = $overrides['password'] ?? bcrypt('password');
    }
    if (Schema::hasColumn('users', 'tenant_id') && isset($overrides['tenant_id'])) {
      $data['tenant_id'] = $overrides['tenant_id'];
    }
    if (Schema::hasColumn('users', 'status')) {
      $data['status'] = $overrides['status'] ?? 'active';
    }
    if (Schema::hasColumn('users', 'email_verified_at')) {
      $data['email_verified_at'] = $overrides['email_verified_at'] ?? now();
    }

    $data = array_merge($data, $overrides);

    $id = DB::table('users')->insertGetId($data);

    return ['id' => $id, 'email' => $email];
  }

  /**
   * Create an admin user and attach admin role if your schema supports roles.
   *
   * Returns ['id' => int, 'email' => string].
   */
  protected function seedAdminUser(?int $tenantId = null, array $overrides = []): array
  {
    $user = $this->seedUser(array_merge([
      'name'      => 'Admin User',
      'email'     => 'admin_' . Str::lower(Str::random(6)) . '@example.test',
      'tenant_id' => $tenantId,
      'status'    => 'active',
    ], $overrides));

    // Spatie-style roles
    if (Schema::hasTable('roles')) {
      // Ensure an 'admin' role exists
      $roleId = DB::table('roles')->where('slug', 'admin')->value('id');
      if (!$roleId) {
        $roleId = DB::table('roles')->insertGetId([
          'name'       => 'Admin',
          'slug'       => 'admin',
          'scope'      => Schema::hasColumn('roles', 'scope') ? 'tenant' : null,
          'created_at' => now(),
          'updated_at' => now(),
        ]);
      }

      // Try common pivot tables
      if (Schema::hasTable('role_user')) {
        DB::table('role_user')->updateOrInsert(
          ['role_id' => $roleId, 'user_id' => $user['id']],
          []
        );
      } elseif (Schema::hasTable('model_has_roles')) {
        DB::table('model_has_roles')->updateOrInsert(
          [
            'role_id'    => $roleId,
            'model_type' => 'App\\Models\\User',
            'model_id'   => $user['id'],
          ],
          []
        );
      }
    }

    return $user;
  }
}
