<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Class SubscribersUniquePerTenantTest
 *
 * Purpose:
 * - Verify that subscribers enforce per-tenant uniqueness on (tenant_id, address).
 * - Ensure the global UNIQUE(address) is not present anymore.
 *
 * Assumptions:
 * - Migration 2025_09_23_000200_subscribers_unique_per_tenant.php has been created and runs in testing DB.
 * - MySQL is used (information_schema available).
 */
class SubscribersUniquePerTenantTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    $driver = config('database.connections.' . config('database.default') . '.driver');
    if ($driver !== 'mysql') {
      $this->markTestSkipped('This suite requires MySQL. Current driver: ' . $driver);
    }
  }

  /**
   * Test that the same address can be used in different tenants.
   * @return void
   */
  public function testAllowsSameAddressInDifferentTenants(): void
  {
    $tenantA = $this->createTenant('Tenant A', 'tenant-a');
    $tenantB = $this->createTenant('Tenant B', 'tenant-b');

    // Insert for tenant 1
    DB::table('subscribers')->insert([
      'tenant_id'   => $tenantA,
      'address'     => 'user@example.test',
      'address_type'=> 'e',
      'active'      => 1,
      'bounce_count'=> 0,
      'created_at'  => now(),
      'updated_at'  => now(),
    ]);

    // Insert same address for tenant 2 -> should be allowed
    DB::table('subscribers')->insert([
      'tenant_id'   => $tenantB,
      'address'     => 'user@example.test',
      'address_type'=> 'e',
      'active'      => 1,
      'bounce_count'=> 0,
      'created_at'  => now(),
      'updated_at'  => now(),
    ]);

    // If we got here, no UNIQUE violation occurred.
    $this->assertNotSame($tenantA, $tenantB);
  }

  /**
   * Test that duplicate address within the same tenant is rejected.
   * @return void
   */
  public function testRejectsDuplicateAddressWithinSameTenant(): void
  {
    $tenantId = $this->createTenant('Tenant X', 'tenant-x');

    DB::table('subscribers')->insert([
      'tenant_id'   => $tenantId,
      'address'     => 'dup@example.test',
      'address_type'=> 'e',
      'active'      => 1,
      'bounce_count'=> 0,
      'created_at'  => now(),
      'updated_at'  => now(),
    ]);

    $this->expectException(QueryException::class);

    DB::table('subscribers')->insert([
      'tenant_id'   => $tenantId,
      'address'     => 'dup@example.test',
      'address_type'=> 'e',
      'active'      => 1,
      'bounce_count'=> 0,
      'created_at'  => now(),
      'updated_at'  => now(),
    ]);
  }

  /**
   * Test that the composite UNIQUE(tenant_id, address) exists.
   * @param  string  $table
   * @param  string  $indexName
   */
  #[DataProvider('uniqueIndexProvider')]
  public function testCompositeUniqueIndexExists(string $table, string $indexName): void
  {
    $this->assertTrue(
      $this->indexExists($table, $indexName),
      "Expected UNIQUE index {$table}.{$indexName} to exist"
    );
  }

  /**
   * Test Global UNIQUE(address) is gone.
   * @return void
   */
  public function testGlobalUniqueOnAddressIsAbsent(): void
  {
    $db = DB::getDatabaseName();

    $row = DB::selectOne(
      "SELECT COUNT(*) AS c
               FROM (
                    SELECT INDEX_NAME,
                           SUM(1)                                        AS total_cols,
                           SUM(CASE WHEN COLUMN_NAME = 'address' THEN 1 ELSE 0 END) AS match_cols
                      FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA = ?
                       AND TABLE_NAME   = 'subscribers'
                       AND NON_UNIQUE   = 0
                     GROUP BY INDEX_NAME
               ) t
              WHERE t.total_cols = 1
                AND t.match_cols = 1",
      [$db]
    );

    $this->assertTrue(isset($row->c), 'Could not query information_schema for subscribers indexes');
    $this->assertSame(0, (int) $row->c, 'Did not expect any global UNIQUE(address) index on subscribers');
  }

  /**
   * Data provider for testCompositeUniqueIndexExists.
   * @return array<int, array{string, string}>
   */
  public static function uniqueIndexProvider(): array
  {
    return [
      ['subscribers', 'subscribers_tenant_address_unique'],
    ];
  }

  /**
   * Check if an index exists on a table.
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
                AND TABLE_NAME   = ?
                AND INDEX_NAME   = ?
              LIMIT 1",
      [$db, $table, $indexName]
    );

    return isset($row->c) ? ((int) $row->c > 0) : false;
  }

  /**
   * Create a tenant row and return its ID.
   *
   * @param  string  $name
   * @param  string  $slug
   * @param  string  $status
   * @return int
   */
  private function createTenant(string $name, string $slug, string $status = 'active'): int
  {
    return (int) DB::table('tenants')->insertGetId([
      'name'       => $name,
      'slug'       => $slug,
      'status'     => $status,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }
}
