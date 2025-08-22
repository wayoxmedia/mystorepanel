<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Add missing platform fields to tenants:
   * - primary_domain: optional main domain for the tenant
   * - allowed_origins: JSON array of allowed CORS origins
   * - status: lifecycle status (active/suspended/etc.)
   *
   * This migration is safe to run on top of your existing tenants table.
   */
  public function up(): void
  {
    Schema::table('tenants', function (Blueprint $table) {
      $table->string('primary_domain')
        ->nullable()
        ->after('slug');
      $table->index('primary_domain'); // speeds up tenant lookups by domain

      // Store an array of origins (e.g., ["https://pepitas.com", "https://app.pepitas.com"])
      $table->json('allowed_origins')
        ->nullable()
        ->after('primary_domain');

      // status (string, default 'active') + index
      $table->enum('status', ['active','suspended','pending'])
        ->default('pending')
        ->after('allowed_origins');
      $table->index('status');
    });
  }

  /**
   * Reverse only the columns added by this migration.
   * Uses dropIndex with inferred names for safety.
   */
  public function down(): void
  {
    // Drop status (index first, then column)
    Schema::table('tenants', function (Blueprint $table) {
      $table->dropIndex(['status']); // tenants_status_index
      $table->dropColumn('status');

      // Drop allowed_origins
      $table->dropColumn('allowed_origins');

      // Drop primary_domain (index first, then column)
      $table->dropIndex(['primary_domain']); // tenants_primary_domain_index
      $table->dropColumn('primary_domain');
    });
  }
};
