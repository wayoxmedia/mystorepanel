<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Create roles and role_user pivot, and upsert base roles.
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

    Schema::create('role_user', function (Blueprint $table) {
      $table->unsignedBigInteger('user_id');
      $table->unsignedBigInteger('role_id');
      $table->timestamps();

      $table->primary(['user_id', 'role_id']);
      $table->foreign('user_id')
        ->references('id')
        ->on('users')
        ->cascadeOnDelete();
      $table->foreign('role_id')
        ->references('id')
        ->on('roles')
        ->cascadeOnDelete();
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
    Schema::dropIfExists('role_user');
    Schema::dropIfExists('roles');
  }
};
