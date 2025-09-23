<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class DeprecateTemplateSlugPhase1
 *
 * Purpose:
 * - Ensure templates.slug has a UNIQUE index (source of truth).
 * - Deprecate tenants.template_slug by making it nullable and removing any default.
 *   (Read-only, legacy compatibility for now;
 *   will be dropped in a later phase.)
 *
 * Assumptions:
 * - Tables 'templates' and 'tenants' exist.
 * - Column 'templates.slug' exists and is the canonical slug for a template.
 * - Column 'tenants.template_slug' exists
 *   but should no longer be written by the app.
 *
 * Notes:
 * - This migration does not remove tenants.template_slug yet.
 *   It only marks it as deprecated
 *   by relaxing constraints (nullable, no default).
 *   A later migration will DROP the column.
 * - We use DB::getDatabaseName() rather than env()
 *   to be robust across test connections.
 */
return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    // 1) templates.slug -> UNIQUE
    if (Schema::hasTable('templates')
      && Schema::hasColumn('templates', 'slug')
    ) {
      $exists = $this->indexExists(
        'templates',
        'templates_slug_unique');
      if (!$exists ) {
        Schema::table('templates', function (Blueprint $table): void {
          $table->unique('slug', 'templates_slug_unique');
        });
        info(
          '[DeprecateTemplateSlugPhase1] Created UNIQUE index templates.slug'
        );
      }
    }

    // 2) tenants.template_slug -> nullable + remove default (deprecated)
    if (Schema::hasTable('tenants')
      && Schema::hasColumn('tenants', 'template_slug')
    ) {
      try {
        // Make column NULLABLE and drop DEFAULT (keep current type/length).
        // We cannot ALTER with "MODIFY COLUMN ... DEFAULT NULL"
        // because that sets an explicit default.
        // We want no DEFAULT at all, so just MODIFY ... NULL.
        DB::statement('
          ALTER TABLE `tenants`
          MODIFY `template_slug`
          VARCHAR(191) NULL
        ');
        info('[DeprecateTemplateSlugPhase1] tenants.template_slug
          set to NULLABLE without DEFAULT (deprecated)');
      } catch (Throwable) {
        // If current length differs, try to detect length and re-apply with NULL
        try {
          $len = $this->varcharLength('tenants', 'template_slug') ?? 191;
          DB::statement("
            ALTER TABLE `tenants`
            MODIFY `template_slug`
            VARCHAR({$len}) NULL
          ");
          info('[DeprecateTemplateSlugPhase1] tenants.template_slug
           set to NULLABLE (detected length) without DEFAULT');
        } catch (Throwable $e2) {
          info("[DeprecateTemplateSlugPhase1] Could not modify
           tenants.template_slug: {$e2->getMessage()}");
        }
      }
    }
  }

  /**
   * Reverse the migrations.
   *
   * Notes:
   * - We drop the UNIQUE on templates.slug for rollback symmetry.
   * - We cannot reliably restore the previous NOT NULL/DEFAULT
   *   on tenants.template_slug (unknown prior default).
   *   If needed, add a dedicated migration to reintroduce the exact default.
   */
  public function down(): void
  {
    // 1) templates.slug UNIQUE -> drop if exists
    if ($this->indexExists('templates', 'templates_slug_unique')) {
      Schema::table('templates', function (Blueprint $table): void {
        $table->dropUnique('templates_slug_unique');
      });
    }

    // 2) tenants.template_slug rollback: best-effort only
    // (make NOT NULL if it was previously)
    // We do NOT re-add a default here because we don't know the original value.
    if (Schema::hasTable('tenants')
      && Schema::hasColumn('tenants', 'template_slug')
    ) {
      try {
        $len = $this->varcharLength('tenants', 'template_slug') ?? 191;
        DB::statement("
          ALTER TABLE `tenants`
          MODIFY `template_slug`
          VARCHAR({$len}) NOT NULL
        ");
      } catch (Throwable $e) {
        info("[DeprecateTemplateSlugPhase1] Could not revert
         tenants.template_slug nullability: {$e->getMessage()}");
      }
    }
  }

  /**
   * Check if a named index exists on a table in the current database.
   *
   * @param  string  $table
   * @param  string  $indexName
   * @return bool
   */
  private function indexExists(string $table, string $indexName): bool
  {
    $db = DB::getDatabaseName();
    if (empty($db)) {
      return false;
    }

    $row = DB::selectOne(
      "SELECT COUNT(1) AS c
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME   = ?
                AND INDEX_NAME   = ?
              LIMIT 1",
      [$db, $table, $indexName]
    );

    return isset($row->c) && (int)$row->c > 0;
  }

  /**
   * Try to detect VARCHAR length for a column.
   *
   * @param  string  $table
   * @param  string  $column
   * @return int|null
   */
  private function varcharLength(string $table, string $column): ?int
  {
    $db = DB::getDatabaseName();
    if (empty($db)) {
      return null;
    }

    $row = DB::selectOne(
      "SELECT CHARACTER_MAXIMUM_LENGTH AS len, DATA_TYPE AS dt
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME   = ?
                AND COLUMN_NAME  = ?
              LIMIT 1",
      [$db, $table, $column]
    );

    if (! $row) {
      return null;
    }

    // Only return length if it's a VARCHAR-like column
    if (isset($row->dt)
      && stripos((string) $row->dt, 'char') !== false
    ) {
      return isset($row->len) ? (int) $row->len : null;
    }

    return null;
  }
};
