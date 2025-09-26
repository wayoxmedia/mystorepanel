<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Class DeprecateTemplateSlugPhase1Test
 *
 * Purpose:
 * - Ensure templates.slug is UNIQUE.
 * - Ensure tenants.template_slug is nullable and has no DEFAULT.
 *
 * Assumptions:
 * - Migration 2025_09_23_000300_deprecate_template_slug_phase_1.php has been created.
 * - MySQL driver (information_schema available).
 */
class DeprecateTemplateSlugPhase1Test extends TestCase
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
   * Test that templates.slug has a UNIQUE constraint.
   * @return void
   */
  public function testTemplatesSlugIsUnique(): void
  {
    // Insert a template with slug
    DB::table('templates')->insert([
      'name'       => 'Modern',
      'slug'       => 'modern',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $this->expectException(QueryException::class);

    // Insert same slug again -> should violate UNIQUE
    DB::table('templates')->insert([
      'name'       => 'Modern Copy',
      'slug'       => 'modern',
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

  /**
   * Test that tenants.template_slug is nullable.
   * @return void
   */
  public function testTenantsTemplateSlugIsNullable(): void
  {
    // Insert tenant with template_slug = null
    DB::table('tenants')->insert([
      'name'          => 'Tenant With Null TemplateSlug',
      'slug'          => 't-null-ts',
      'status'        => 'active',
      'template_slug' => null,
      'created_at'    => now(),
      'updated_at'    => now(),
    ]);

    $row = DB::table('tenants')
      ->where('slug', 't-null-ts')
      ->first();
    $this->assertNotNull($row, 'Tenant not inserted');
    $this->assertNull(
      $row->template_slug,
      'Expected tenants.template_slug to be NULLABLE'
    );
  }

  /**
   * Test that tenants.template_slug has no DEFAULT value.
   * @return void
   */
  public function testTenantsTemplateSlugHasNoDefault(): void
  {
    if (! $this->columnExists('tenants', 'template_slug')) {
      $this->markTestSkipped('tenants.template_slug not present (already removed).');
    }

    $db  = DB::getDatabaseName();
    $row = DB::selectOne(
      "SELECT COLUMN_DEFAULT AS dflt
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME   = 'tenants'
                AND COLUMN_NAME  = 'template_slug'
              LIMIT 1",
      [$db]
    );

    $this->assertNotNull(
      $row,
      'tenants.template_slug not found in information_schema'
    );
    $this->assertNull(
      $row->dflt,
      'Expected tenants.template_slug to have no DEFAULT'
    );
  }

  /**
   * Check if a column exists in a table in the current database.
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
                AND TABLE_NAME   = ?
                AND COLUMN_NAME  = ?
              LIMIT 1",
      [$db, $table, $column]
    );

    return isset($row->c) && (int)$row->c > 0;
  }
}
