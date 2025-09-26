<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CreateThemesTable
 *
 * Purpose:
 * Create the 'themes' table scoped to tenants, with a per-tenant unique slug,
 * and room for JSON-configurable options.
 *
 * Assumptions:
 * - 'tenants' table exists (id BIGINT unsigned).
 *
 * Notes:
 * - ON DELETE CASCADE so themes are removed when a tenant is deleted.
 * - Slug is unique per tenant (tenant_id, slug).
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('themes', function (Blueprint $table): void {
      $table->bigIncrements('id');

      $table->unsignedBigInteger('tenant_id');
      $table->string('name', 191);
      $table->string('slug', 191);
      $table->string('status', 20)->default('active'); // active|draft|archived (adjust as needed)
      $table->text('description')->nullable();

      // JSON config (colors, fonts, templates mapping, etc.)
      $table->json('config')->nullable();

      $table->timestamps();
      $table->softDeletes();

      // Indexes & constraints
      $table->unique(['tenant_id', 'slug'], 'themes_tenant_slug_unique');
      $table->index(['tenant_id'], 'themes_tenant_id_idx');

      $table->foreign('tenant_id', 'themes_tenant_id_fk')
        ->references('id')->on('tenants')
        ->onDelete('CASCADE')
        ->onUpdate('CASCADE');
    });
  }

  public function down(): void
  {
    Schema::table('themes', function (Blueprint $table): void {
      // Drop FKs first for clean teardown
      if (Schema::hasColumn('themes', 'tenant_id')) {
        $table->dropForeign('themes_tenant_id_fk');
      }
    });

    Schema::dropIfExists('themes');
  }
};
