<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Create roles table, and upsert base roles.
   */
  public function up(): void
  {
    Schema::create('roles', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('slug')->unique();
      $table->enum('scope', ['platform', 'tenant'])
        ->default('tenant')
        ->index();
      $table->timestamps();
    });

    // Upsert base roles
    $now = now();
    DB::table('roles')->upsert(
      [
        [
          'name' => 'Platform Super Admin',
          'slug' => 'platform_super_admin',
          'scope' => 'platform',
          'created_at' => $now,
          'updated_at' => $now
        ],
        [
          'name' => 'Tenant Owner',
          'slug' => 'tenant_owner',
          'scope' => 'tenant',
          'created_at' => $now,
          'updated_at' => $now
        ],
        ['name' => 'Tenant Admin',
          'slug' => 'tenant_admin',
          'scope' => 'tenant',
          'created_at' => $now,
          'updated_at' => $now
        ],
        [
          'name' => 'Tenant Editor',
          'slug' => 'tenant_editor',
          'scope' => 'tenant',
          'created_at' => $now,
          'updated_at' => $now
        ],
        [
          'name' => 'Tenant Viewer',
          'slug' => 'tenant_viewer',
          'scope' => 'tenant',
          'created_at' => $now,
          'updated_at' => $now
        ],
      ],
      ['slug'],
      ['name', 'scope', 'updated_at']
    );
  }

  public function down(): void
  {
    Schema::dropIfExists('roles');
  }
};
