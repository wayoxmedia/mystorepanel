<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Class SchemaHardeningRound1Test
 *
 * Purpose:
 * Validate that the first hardening migration applied:
 *  - UNIQUE index on tenants.primary_domain
 *  - Removal of DEFAULT from users.role_id
 *  - Dropped redundant non-unique index on invitations.token (but kept a UNIQUE on token)
 *  - Composite indexes: contacts(tenant_id, email) and audit_logs(subject_type, subject_id)
 *  - (Smoke) Database default collation is utf8mb4-family
 *
 * Assumptions:
 * - Migration 2025_09_22_000001_schema_hardening_round_1.php has been created.
 * - Laravel testing DB is accessible and migrations run via RefreshDatabase.
 * - MySQL/Aurora MySQL 8.x; information_schema is available.
 */
class SchemaHardeningRound1Test extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    // Skip whole suite if not using MySQL (information_schema not available elsewhere)
    $driver = config('database.connections.' . config('database.default') . '.driver');
    if ($driver !== 'mysql') {
      $this->markTestSkipped('This suite requires MySQL. Current driver: ' . $driver);
    }
  }

  /**
   * Test that tenants.primary_domain has a UNIQUE constraint,
   * allowing multiple NULLs but no duplicate non-NULLs.
   *
   * (Only applies to MySQL/Aurora MySQL, not SQLite in-memory used in tests.)
   * @return void
   */
  public function testTenantsPrimaryDomainIsUnique(): void
  {
    // (igual que ya lo tenías)
    DB::table('tenants')->insert([
      'name'           => 'Tenant Null Domain 1',
      'slug'           => 't-null-1',
      'status'         => 'active',
      'primary_domain' => null,
      'created_at'     => now(),
      'updated_at'     => now(),
    ]);

    DB::table('tenants')->insert([
      'name'           => 'Tenant Null Domain 2',
      'slug'           => 't-null-2',
      'status'         => 'active',
      'primary_domain' => null,
      'created_at'     => now(),
      'updated_at'     => now(),
    ]);

    DB::table('tenants')->insert([
      'name'           => 'Tenant With Domain',
      'slug'           => 't-wd-1',
      'status'         => 'active',
      'primary_domain' => 'foo.example.test',
      'created_at'     => now(),
      'updated_at'     => now(),
    ]);

    $this->expectException(QueryException::class);

    DB::table('tenants')->insert([
      'name'           => 'Tenant With Domain Copy',
      'slug'           => 't-wd-2',
      'status'         => 'active',
      'primary_domain' => 'foo.example.test',
      'created_at'     => now(),
      'updated_at'     => now(),
    ]);
  }

  /**
   * Test that users.role_id has no DEFAULT value.
   *
   * @return void
   */
  public function testUsersRoleIdHasNoDefault(): void
  {
    $db = DB::getDatabaseName();
    $row = DB::selectOne(
      "SELECT COLUMN_DEFAULT AS dflt
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = 'users'
                AND COLUMN_NAME = 'role_id'
              LIMIT 1",
      [$db]
    );

    $this->assertNotNull($row, 'users.role_id column not found in information_schema');
    $this->assertNull($row->dflt, 'Expected users.role_id to have no DEFAULT');
  }

  /**
   * Test that composite indexes exist as expected.
   * @param  string  $table
   * @param  string  $indexName
   * @return void
   */
  #[DataProvider('compositeIndexProvider')]
  public function testCompositeIndexesExist(string $table, string $indexName): void
  {
    $this->assertTrue(
      $this->indexExists($table, $indexName),
      "Expected composite index {$table}.{$indexName} to exist"
    );
  }

  /**
   * Data provider for testCompositeIndexesExist.
   * @return array<int, array{string, string}>
   */
  public static function compositeIndexProvider(): array
  {
    return [
      ['contacts', 'contacts_tenant_email_idx'],
      ['audit_logs', 'audit_logs_subject_type_id_idx'],
    ];
  }

  /**
   * Ensure the redundant non-unique index on invitations.token is gone,
   * but there is at least one UNIQUE index on token.
   *
   * @return void
   */
  public function testInvitationsTokenIndexesAreCorrect(): void
  {
    // Redundant non-unique index should be gone
    $this->assertFalse(
      $this->indexExists('invitations', 'invitations_token_idx'),
      'Did not expect invitations_token_idx to exist'
    );

    // There must be some UNIQUE index that includes column 'token'
    $db = DB::getDatabaseName();
    $uniqueCount = DB::selectOne(
      "SELECT COUNT(*) AS c
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = 'invitations'
                AND COLUMN_NAME = 'token'
                AND NON_UNIQUE = 0",
      [$db]
    );

    $this->assertTrue(
      isset($uniqueCount->c) && (int) $uniqueCount->c > 0,
      'Expected a UNIQUE index on invitations.token'
    );
  }

  /**
   * Smoke check that database default collation is utf8mb4-family.
   * Not strictly required for correctness, but good to lock in defaults.
   *
   * @return void
   */
  public function testDatabaseDefaultCollationIsUtf8mb4Family(): void
  {
    $db = DB::getDatabaseName();

    $row = DB::selectOne(
      "SELECT DEFAULT_CHARACTER_SET_NAME AS cset, DEFAULT_COLLATION_NAME AS coll
               FROM information_schema.SCHEMATA
              WHERE SCHEMA_NAME = ?
              LIMIT 1",
      [$db]
    );

    $this->assertNotNull($row, 'Database not found in information_schema.SCHEMATA');
    $this->assertStringStartsWith('utf8mb4', (string) $row->cset, 'Expected utf8mb4 character set');
    $this->assertStringStartsWith('utf8mb4', (string) $row->coll, 'Expected utf8mb4 collation');
  }

  /**
   * Check if a named index exists on a table (current database).
   *
   * @param  string  $table
   * @param  string  $indexName
   * @return bool
   */
  private function indexExists(string $table, string $indexName): bool
  {
    $db = DB::getDatabaseName();

    $row = DB::selectOne(
      "SELECT COUNT(1) AS c
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND INDEX_NAME = ?
              LIMIT 1",
      [$db, $table, $indexName]
    );

    return isset($row->c) ? ((int) $row->c > 0) : false;
  }

  /**
   * Check if a named column exists on a table (current database).
   *
   * @param  string  $table
   * @param  string  $column
   * @return bool
   */
  private function columnExists(string $table, string $column): bool
  {
    $db = DB::getDatabaseName();

    $row = DB::selectOne(
      "SELECT COUNT(1) AS c
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
              LIMIT 1",
      [$db, $table, $column]
    );

    return isset($row->c) ? ((int) $row->c > 0) : false;
  }
}
