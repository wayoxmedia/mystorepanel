<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SmokeTest extends TestCase
{
  use RefreshDatabase;

  /**
   * This test guarantees we are running in the testing env
   * and the DB name looks like a dedicated test database.
   */
  public function test_testing_env_and_database_are_safe(): void
  {
    // APP_ENV must be "testing"
    $this->assertTrue(
      app()->environment('testing'),
      'APP_ENV should be "testing".'
    );

    // Active DB connection must be mysql_testing
    $defaultConnection = Config::get('database.default');
    $this->assertSame(
      'mysql_testing', $defaultConnection,
      'DB connection should be "mysql_testing".'
    );

    // Database name must end with "_test"
    $dbName = (string) Config::get("database.connections.{$defaultConnection}.database");
    $this->assertNotSame(
      '',
      $dbName,
      'Database name must not be empty.'
    );
    $this->assertMatchesRegularExpression(
      '/_test$/i',
      $dbName,
      'Database name must end with "_test".'
    );
  }

  /**
   * Quick sanity check that the app boots and DB is reachable without touching prod.
   * RefreshDatabase runs migrations on the testing DB before this.
   */
  public function test_application_boots_and_db_accepts_simple_query(): void
  {
    // Basic config sanity
    $this->assertNotEmpty(
      config('app.key'),
      'APP_KEY should be set in .env.testing'
    );
    $this->assertSame(
      'array',
      config('mail.default'),
      'MAIL_MAILER should be "array" in testing.'
    );
    $this->assertSame(
      'sync',
      config('queue.default'),
      'QUEUE_CONNECTION should be "sync" in testing.'
    );

    // Simple DB check (no writes)
    $result = DB::select('SELECT 1 AS one');
    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertEquals(
      1,
      (int) ($result[0]->one ?? 0)
    );
  }
}
