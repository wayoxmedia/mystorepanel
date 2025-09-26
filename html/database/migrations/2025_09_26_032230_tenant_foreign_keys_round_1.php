<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TenantForeignKeysRound1
 *
 * Purpose:
 * Ensure core MSP tables are properly related to tenants with foreign keys and useful indexes.
 * Also enforce the FK from tenants.template_id to templates.id.
 *
 * Assumptions:
 * - MySQL 8.x.
 * - Tables exist: tenants, users, sites, themes, subscribers, contacts, pages, templates.
 * - Columns `tenant_id` exist in: users, sites, themes, subscribers, contacts, pages.
 * - Column `template_id` exists (nullable) in tenants.
 *
 * Notes:
 * - FKs are added idempotently (we check existence before adding).
 * - ON DELETE defaults chosen pragmatically for SaaS:
 *   * Child rows are CASCADE-deleted when a tenant is soft/hard-deleted at DB level.
 *     (Your app uses SoftDeletes, so hard-deletes are rare; CASCADE is a safety net)
 *   * ON UPDATE CASCADE to keep consistency if ids are ever changed (rare).
 * - For templates, we use ON DELETE SET NULL so tenants survive if a template is removed.
 */
return new class extends Migration
{
  public function up(): void
  {
    // 1) tenants.template_id → templates.id (SET NULL)
    if ($this->tableHasColumn('tenants', 'template_id')) {
      $this->addForeignKeyIfMissing(
        table: 'tenants',
        fkName: 'tenants_template_id_fk',
        columns: ['template_id'],
        refTable: 'templates',
        onDelete: 'SET NULL'
      );
    }

    // 2) Child tables with tenant_id → tenants.id (CASCADE)
    $childTables = [
      'users',
      'sites',
      'themes',
      'subscribers',
      'contacts',
      'pages',
    ];

    foreach ($childTables as $tbl) {
      if ($this->tableHasColumn($tbl, 'tenant_id')) {
        $this->addForeignKeyIfMissing(
          table: $tbl,
          fkName: "{$tbl}_tenant_id_fk",
          columns: ['tenant_id'],
          refTable: 'tenants',
          onDelete: 'CASCADE'
        );

        // Helpful index for tenant scoping (if not already present)
        $this->addIndexIfMissing(
          table: $tbl,
          indexName: "{$tbl}_tenant_id_idx"
        );
      }
    }
  }

  public function down(): void
  {
    // Drop FKs (defensively), then indexes we might have added.
    $this->dropForeignKeyIfExists('tenants', 'tenants_template_id_fk');

    foreach (['users','sites','themes','subscribers','contacts','pages'] as $tbl) {
      $this->dropForeignKeyIfExists($tbl, "{$tbl}_tenant_id_fk");
      $this->dropIndexIfExists($tbl, "{$tbl}_tenant_id_idx");
    }
  }

  // ---------- Helpers ----------

  /**
   * Add a foreign key if it doesn't already exist.
   *
   * @param  string            $table
   * @param  string            $fkName
   * @param  array<int,string> $columns
   * @param  string            $refTable
   * @param  string            $onDelete
   * @return void
   */
  private function addForeignKeyIfMissing(
    string $table,
    string $fkName,
    array $columns,
    string $refTable,
    string $onDelete = 'RESTRICT'
  ): void {
    $onUpdate = 'CASCADE';
    $refColumns = ['id'];
    if (!Schema::hasTable($table) || ! Schema::hasTable($refTable)) {
      return;
    }

    if ($this->foreignKeyExists($table, $fkName)) {
      return;
    }

    Schema::table(
      $table,
      function (Blueprint $tbl) use (
        $fkName, $columns, $refTable, $refColumns, $onDelete, $onUpdate
      ): void {
        $tbl->foreign($columns, $fkName)
          ->references($refColumns)->on($refTable)
          ->onDelete($onDelete)
          ->onUpdate($onUpdate);
      }
    );

    info("[MspTenantForeignKeysRound1] Added FK {$table}.{$fkName} → {$refTable}("
      . implode(',', $refColumns) . ')');
  }

  /**
   * Add an index if it doesn't exist.
   *
   * @param  string             $table
   * @param  string             $indexName
   * @return void
   */
  private function addIndexIfMissing(string $table, string $indexName): void
  {
    $columns = ['tenant_id'];
    if (! Schema::hasTable($table)) {
      return;
    }

    if ($this->indexExists($table, $indexName)) {
      return;
    }

    Schema::table(
      $table,
      function (Blueprint $tbl) use ($indexName, $columns): void {
        $tbl->index($columns, $indexName);
      }
    );

    info("[MspTenantForeignKeysRound1] Added index {$table}.{$indexName}");
  }

  private function dropForeignKeyIfExists(string $table, string $fkName): void
  {
    if (!Schema::hasTable($table)
      || !$this->foreignKeyExists($table, $fkName)
    ) {
      return;
    }

    Schema::table($table, function (Blueprint $tbl) use ($fkName): void {
      $tbl->dropForeign($fkName);
    });

    info("[MspTenantForeignKeysRound1] Dropped FK {$table}.{$fkName}");
  }

  private function dropIndexIfExists(string $table, string $indexName): void
  {
    if (!Schema::hasTable($table)
      || !$this->indexExists($table, $indexName)
    ) {
      return;
    }

    Schema::table($table, function (Blueprint $tbl) use ($indexName): void {
      $tbl->dropIndex($indexName);
    });

    info("[MspTenantForeignKeysRound1] Dropped index {$table}.{$indexName}");
  }

  private function foreignKeyExists(string $table, string $fkName): bool
  {
    $db = DB::getDatabaseName();

    $row = DB::selectOne(
      "SELECT COUNT(*) AS c
               FROM information_schema.REFERENTIAL_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = ?
                AND CONSTRAINT_NAME   = ?
                AND TABLE_NAME        = ?",
      [$db, $fkName, $table]
    );

    return isset($row->c) && (int)$row->c > 0;
  }

  private function indexExists(string $table, string $indexName): bool
  {
    $db = DB::getDatabaseName();

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

  private function tableHasColumn(string $table, string $column): bool
  {
    return Schema::hasTable($table) && Schema::hasColumn($table, $column);
  }
};
