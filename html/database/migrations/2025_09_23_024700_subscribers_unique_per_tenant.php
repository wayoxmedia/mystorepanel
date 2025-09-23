<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class SubscribersUniquePerTenant
 *
 * Purpose:
 * - Enforce per-tenant uniqueness for subscribers by adding UNIQUE(tenant_id, address).
 * - Drop any global UNIQUE index on `address` (UNIQUE(address)), which is too restrictive for multi-tenant SaaS.
 *
 * Assumptions:
 * - Table 'subscribers' exists with columns: tenant_id (nullable or not) and address (string).
 * - MySQL/Aurora MySQL 8.x; information_schema is accessible.
 *
 * Notes:
 * - MySQL UNIQUE allows multiple NULLs; leaving tenant_id nullable does not block multiple (NULL, same address).
 *   That means tenants with NULL won't collide. If you desire stricter guarantees, consider making tenant_id NOT NULL later.
 * - We detect and drop any UNIQUE index that covers ONLY the column `address`.
 * - We create the composite UNIQUE index if it doesn't already exist.
 */
return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    if (! Schema::hasTable('subscribers')) {
      return;
    }

    // 1) Ensure composite UNIQUE(tenant_id, address)
    if ($this->needsCompositeUnique()) {
      Schema::table('subscribers', function (Blueprint $table): void {
        $table->unique(['tenant_id', 'address'], 'subscribers_tenant_address_unique');
      });
      info('[SubscribersUniquePerTenant] Created UNIQUE index subscribers(tenant_id, address).');
    }

    // 2) Drop any global UNIQUE(address) indexes (name-agnostic)
    $uniqueOnAddress = $this->uniqueIndexesOnSingleColumn('subscribers', 'address');

    foreach ($uniqueOnAddress as $indexName) {
      // Avoid dropping our new composite unique by name
      if ($indexName !== 'subscribers_tenant_address_unique') {
        try {
          Schema::table('subscribers', function (Blueprint $table) use ($indexName): void {
            $table->dropUnique($indexName);
          });
          info("[SubscribersUniquePerTenant] Dropped UNIQUE index `$indexName` on subscribers.address");
        } catch (Throwable) {
          // Fallback to raw SQL in case Laravel can't resolve the index
          try {
            DB::statement('ALTER TABLE `subscribers` DROP INDEX `' . str_replace('`', '``', $indexName) . '`');
            info("[SubscribersUniquePerTenant] Dropped UNIQUE index `$indexName` via raw SQL");
          } catch (Throwable $e2) {
            info("[SubscribersUniquePerTenant] Could not drop index `$indexName`: {$e2->getMessage()}");
          }
        }
      }
    }
  }

  /**
   * Reverse the migrations.
   *
   * Note:
   * - Re-create the global UNIQUE(address) (for rollback symmetry).
   * - Drop the composite UNIQUE(tenant_id, address).
   */
  public function down(): void
  {
    if (! Schema::hasTable('subscribers')) {
      return;
    }

    // Drop composite UNIQUE(tenant_id, address) if exists
    if ($this->indexExists('subscribers', 'subscribers_tenant_address_unique')) {
      Schema::table('subscribers', function (Blueprint $table): void {
        $table->dropUnique('subscribers_tenant_address_unique');
      });
    }

    // Re-create global UNIQUE(address) if absent (use a conventional name)
    if (! $this->hasUniqueOnColumn('subscribers', 'address')) {
      Schema::table('subscribers', function (Blueprint $table): void {
        $table->unique('address', 'subscribers_address_unique');
      });
    }
  }

  /**
   * Determine if we need to create UNIQUE(tenant_id, address).
   *
   * @return bool
   */
  private function needsCompositeUnique(): bool
  {
    $db = DB::getDatabaseName();
    if ($db === '' || $db === null) {
      return false;
    }

    // Does a UNIQUE index exist that includes both columns exclusively or at least covers them in any order?
    $row = DB::selectOne(
      "SELECT COUNT(*) AS c
               FROM information_schema.STATISTICS s
              WHERE s.TABLE_SCHEMA = ?
                AND s.TABLE_NAME   = 'subscribers'
                AND s.NON_UNIQUE   = 0
                AND EXISTS (
                      SELECT 1
                        FROM information_schema.STATISTICS s2
                       WHERE s2.TABLE_SCHEMA = s.TABLE_SCHEMA
                         AND s2.TABLE_NAME   = s.TABLE_NAME
                         AND s2.INDEX_NAME   = s.INDEX_NAME
                         AND s2.COLUMN_NAME  = 'tenant_id'
                )
                AND EXISTS (
                      SELECT 1
                        FROM information_schema.STATISTICS s3
                       WHERE s3.TABLE_SCHEMA = s.TABLE_SCHEMA
                         AND s3.TABLE_NAME   = s.TABLE_NAME
                         AND s3.INDEX_NAME   = s.INDEX_NAME
                         AND s3.COLUMN_NAME  = 'address'
                )",
      [$db]
    );

    return ! (isset($row->c) && (int) $row->c > 0);
  }

  /**
   * Return the names of UNIQUE indexes that cover ONLY the given single column.
   *
   * @param  string  $table
   * @param  string  $column
   * @return list<string>
   */
  private function uniqueIndexesOnSingleColumn(string $table, string $column): array
  {
    $db = DB::getDatabaseName();
    if ($db === '' || $db === null) {
      return [];
    }

    // Find unique indexes where ALL columns = exactly the given column.
    $sql = <<<SQL
SELECT t.INDEX_NAME
FROM (
    SELECT INDEX_NAME,
           SUM(1)                           AS total_cols,
           SUM(CASE WHEN COLUMN_NAME = ? THEN 1 ELSE 0 END) AS match_cols
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = ?
      AND TABLE_NAME   = ?
      AND NON_UNIQUE   = 0
    GROUP BY INDEX_NAME
) t
WHERE t.total_cols = 1
  AND t.match_cols = 1
SQL;

    $rows = DB::select($sql, [$column, $db, $table]);

    return array_values(array_map(
      static fn ($r) => (string) $r->INDEX_NAME,
      $rows
    ));
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
    if ($db === '' || $db === null) {
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

    return isset($row->c) ? ((int) $row->c > 0) : false;
  }

  /**
   * Check if there exists any UNIQUE index that includes the given column (single or composite).
   *
   * @param  string  $table
   * @param  string  $column
   * @return bool
   */
  private function hasUniqueOnColumn(string $table, string $column): bool
  {
    $db = DB::getDatabaseName();
    if ($db === '' || $db === null) {
      return false;
    }

    $row = DB::selectOne(
      "SELECT COUNT(*) AS c
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME   = ?
                AND COLUMN_NAME  = ?
                AND NON_UNIQUE   = 0",
      [$db, $table, $column]
    );

    return isset($row->c) ? ((int) $row->c > 0) : false;
  }
};
