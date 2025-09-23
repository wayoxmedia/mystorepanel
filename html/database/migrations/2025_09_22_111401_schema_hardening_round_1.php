<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class SchemaHardeningRound1
 *
 * Purpose:
 * - Align database default charset/collation to utf8mb4 (A).
 * - Add UNIQUE index to tenants.primary_domain (B-1).
 * - Remove DEFAULT from users.role_id (C).
 * - Ensure UNIQUE on invitations.token and drop any non-unique index on token (D).
 * - Add composite index contacts(tenant_id, email) (F).
 * - Add composite index audit_logs(subject_type, subject_id) (G).
 *
 * Assumptions:
 * - MySQL/Aurora MySQL 8.x is used and information_schema is accessible.
 * - The migration should run against the *active* connection/database.
 *
 * Notes:
 * - We do NOT rely on env() for DB name; we use DB::getDatabaseName().
 * - We log info() messages to help debugging if ALTER DATABASE or index ops fail.
 * - Tenants.primary_domain is nullable; UNIQUE allows multiple NULLs in MySQL.
 */
return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    // ---------------------------
    // A) Align DB default charset/collation to utf8mb4
    // ---------------------------
    $dbName = DB::getDatabaseName();

    if (! empty($dbName)) {
      $attempts = [
        ['utf8mb4', 'utf8mb4_0900_ai_ci'],   // Preferred on MySQL 8.0+
        ['utf8mb4', 'utf8mb4_unicode_ci'],   // Fallback if 0900_* is unavailable
      ];

      $changed = false;
      foreach ($attempts as [$charset, $collation]) {
        try {
          DB::statement(sprintf(
            'ALTER DATABASE `%s` CHARACTER SET %s COLLATE %s',
            str_replace('`', '``', $dbName),
            $charset,
            $collation
          ));
          info("[SchemaHardeningRound1] ALTER DATABASE `$dbName` -> $charset / $collation OK");
          $changed = true;
          break;
        } catch (Throwable $e) {
          info("[SchemaHardeningRound1] ALTER DATABASE `$dbName` attempt failed: {$e->getMessage()}");
        }
      }

      if (! $changed) {
        info("[SchemaHardeningRound1] Could not ALTER DATABASE `$dbName`. " .
          "Run it manually or ensure privileges. Proceeding with table-level changes.");
      }
    } else {
      info('[SchemaHardeningRound1] DB::getDatabaseName() returned empty; skipping ALTER DATABASE.');
    }

    // ---------------------------
    // B-1) tenants.primary_domain UNIQUE
    // ---------------------------
    if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'primary_domain')) {
      if (! $this->indexExists('tenants', 'tenants_primary_domain_unique')) {
        Schema::table('tenants', function (Blueprint $table): void {
          $table->unique('primary_domain', 'tenants_primary_domain_unique');
        });
        info('[SchemaHardeningRound1] Created UNIQUE index tenants.primary_domain');
      }
    }

    // ---------------------------
    // C) users.role_id -> remove DEFAULT (keep NOT NULL if possible)
    // ---------------------------
    if (Schema::hasTable('users') && Schema::hasColumn('users', 'role_id')) {
      try {
        DB::statement('ALTER TABLE `users` MODIFY `role_id` BIGINT UNSIGNED NOT NULL');
        info('[SchemaHardeningRound1] users.role_id set to BIGINT UNSIGNED NOT NULL without DEFAULT');
      } catch (\Throwable $e) {
        // If that fails (e.g., due to FK constraints or existing nulls), preserve nullability but drop DEFAULT.
        try {
          $nullable = $this->isColumnNullable('users', 'role_id');
          $nullSql = $nullable ? 'NULL' : 'NOT NULL';
          DB::statement("ALTER TABLE `users` MODIFY `role_id` BIGINT UNSIGNED {$nullSql}");
          info('[SchemaHardeningRound1] users.role_id modified without DEFAULT (preserved nullability)');
        } catch (\Throwable $e2) {
          info("[SchemaHardeningRound1] Could not modify users.role_id: {$e2->getMessage()}");
        }
      }
    }

    // ---------------------------
    // D) invitations: ensure UNIQUE(token) and drop any non-unique index on token
    // ---------------------------
    if (Schema::hasTable('invitations') && Schema::hasColumn('invitations', 'token')) {
      $db = DB::getDatabaseName();

      // 1) Ensure UNIQUE on token
      $uniqueExists = DB::selectOne(
        "SELECT COUNT(*) AS c
                   FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = ?
                    AND TABLE_NAME = 'invitations'
                    AND COLUMN_NAME = 'token'
                    AND NON_UNIQUE = 0",
        [$db]
      );
      if (! (isset($uniqueExists->c) && (int) $uniqueExists->c > 0)) {
        Schema::table('invitations', function (Blueprint $table): void {
          $table->unique('token', 'invitations_token_unique');
        });
        info('[SchemaHardeningRound1] Created UNIQUE index invitations.token');
      }

      // 2) Drop any non-unique indexes on token (whatever their names are)
      $nonUniques = DB::select(
        "SELECT DISTINCT INDEX_NAME
                   FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = ?
                    AND TABLE_NAME = 'invitations'
                    AND COLUMN_NAME = 'token'
                    AND NON_UNIQUE = 1",
        [$db]
      );

      foreach ($nonUniques as $idx) {
        $name = (string) $idx->INDEX_NAME;

        if ($name !== 'PRIMARY' && $name !== 'invitations_token_unique') {
          try {
            Schema::table('invitations', function (Blueprint $table) use ($name): void {
              $table->dropIndex($name);
            });
            info("[SchemaHardeningRound1] Dropped non-unique index `$name` on invitations.token");
          } catch (\Throwable $e) {
            // Fallback to raw SQL if Laravel name resolution fails
            try {
              DB::statement('ALTER TABLE `invitations` DROP INDEX `' . str_replace('`', '``', $name) . '`');
              info("[SchemaHardeningRound1] Dropped non-unique index `$name` via raw SQL");
            } catch (\Throwable $e2) {
              info("[SchemaHardeningRound1] Could not drop index `$name`: {$e2->getMessage()}");
            }
          }
        }
      }
    }

    // ---------------------------
    // F) contacts(tenant_id, email) composite index
    // ---------------------------
    if (Schema::hasTable('contacts') &&
      Schema::hasColumn('contacts', 'tenant_id') &&
      Schema::hasColumn('contacts', 'email')
    ) {
      if (! $this->indexExists('contacts', 'contacts_tenant_email_idx')) {
        Schema::table('contacts', function (Blueprint $table): void {
          $table->index(['tenant_id', 'email'], 'contacts_tenant_email_idx');
        });
        info('[SchemaHardeningRound1] Created index contacts(tenant_id, email)');
      }
    }

    // ---------------------------
    // G) audit_logs(subject_type, subject_id) composite index
    // ---------------------------
    if (Schema::hasTable('audit_logs') &&
      Schema::hasColumn('audit_logs', 'subject_type') &&
      Schema::hasColumn('audit_logs', 'subject_id')
    ) {
      if (! $this->indexExists('audit_logs', 'audit_logs_subject_type_id_idx')) {
        Schema::table('audit_logs', function (Blueprint $table): void {
          $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_type_id_idx');
        });
        info('[SchemaHardeningRound1] Created index audit_logs(subject_type, subject_id)');
      }
    }
  }

  /**
   * Reverse the migrations.
   *
   * Note:
   * - We do NOT try to restore the database default charset/collation.
   * - We drop newly-added indexes and (optionally) recreate a non-unique index on invitations.token.
   */
  public function down(): void
  {
    // B-1) tenants.primary_domain UNIQUE -> drop if exists
    if ($this->indexExists('tenants', 'tenants_primary_domain_unique')) {
      Schema::table('tenants', function (Blueprint $table): void {
        $table->dropUnique('tenants_primary_domain_unique');
      });
    }

    // C) users.role_id default removal cannot be reliably restored (unknown previous default).
    //    If needed, re-add a default in a separate migration.

    // D) invitations: drop UNIQUE(token) and recreate a non-unique index (optional rollback symmetry)
    if (Schema::hasTable('invitations') && Schema::hasColumn('invitations', 'token')) {
      if ($this->indexExists('invitations', 'invitations_token_unique')) {
        Schema::table('invitations', function (Blueprint $table): void {
          $table->dropUnique('invitations_token_unique');
        });
      }
      if (! $this->indexExists('invitations', 'invitations_token_idx')) {
        Schema::table('invitations', function (Blueprint $table): void {
          $table->index('token', 'invitations_token_idx');
        });
      }
    }

    // F) contacts(tenant_id, email) -> drop if exists
    if ($this->indexExists('contacts', 'contacts_tenant_email_idx')) {
      Schema::table('contacts', function (Blueprint $table): void {
        $table->dropIndex('contacts_tenant_email_idx');
      });
    }

    // G) audit_logs(subject_type, subject_id) -> drop if exists
    if ($this->indexExists('audit_logs', 'audit_logs_subject_type_id_idx')) {
      Schema::table('audit_logs', function (Blueprint $table): void {
        $table->dropIndex('audit_logs_subject_type_id_idx');
      });
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
    $dbName = DB::getDatabaseName();

    if (empty($dbName)) {
      return false;
    }

    $row = DB::selectOne(
      "SELECT COUNT(1) AS c
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND INDEX_NAME = ?
              LIMIT 1",
      [$dbName, $table, $indexName]
    );

    return isset($row->c) ? ((int) $row->c > 0) : false;
  }

  /**
   * Determine if a column is nullable using information_schema.
   *
   * @param  string  $table
   * @param  string  $column
   * @return bool
   */
  private function isColumnNullable(string $table, string $column): bool
  {
    $dbName = DB::getDatabaseName();

    if (empty($dbName)) {
      return true;
    }

    $row = DB::selectOne(
      "SELECT IS_NULLABLE
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
              LIMIT 1",
      [$dbName, $table, $column]
    );

    return ! $row ? true : strtoupper((string) $row->IS_NULLABLE) === 'YES';
  }
};
