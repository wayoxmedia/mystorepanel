<?php

namespace Tests;

use Database\Seeders\BaseRolesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

/**
 * Base test case for the application.
 */
abstract class TestCase extends BaseTestCase
{
  use CreatesApplication;

  /** @var $seed bool */
  protected bool $seed = true;

  /** @var $seeder class-string */
  protected string $seeder = BaseRolesSeeder::class;


  /**
   * Set up the test environment.
   *
   * Hard safety guards so tests never touch production data.
   * @throws RuntimeException
   * @return void
   */
  protected function setUp(): void
  {
    parent::setUp();

    // Hard guard: never run tests against non-testing env or non _test DB
    $env = app()->environment();
    if ($env !== 'testing') {
      $msg  = "Current environment is [$env]. ";
      $msg .= "Refusing to run tests in [$env] environment. ";
      $msg .= "Set APP_ENV=testing and use .env.testing.";
      throw new RuntimeException($msg);
    }

    // 2) Guard: ensure the active DB connection points to a dedicated test database.
    // Resolve default connection (config: database.default)
    $defaultConnection = config('database.default');
    $connectionConfig  = config("database.connections.{$defaultConnection}", []);

    $databaseName = (string)($connectionConfig['database'] ?? '');
    if ($databaseName === '') {
      throw new RuntimeException('No database name configured for the active testing connection.');
    }

    // Allow either SQLite in-memory (not used now) OR a DB name that ends with `_test`
    $isSqliteMemory = ($defaultConnection === 'sqlite' && $databaseName === ':memory:');
    $looksLikeTestDb = (bool)preg_match('/_test$/i', $databaseName);

    if (! $isSqliteMemory && ! $looksLikeTestDb) {
      throw new RuntimeException(sprintf(
        'Refusing to run tests against database [%s]. Use a database whose name ends with "_test".',
        $databaseName
      ));
    }

    // 3) Optional guard: enforce that the testing connection name matches expectation.
    // If you prefer flexibility, you can comment this block out.
    $expectedConnection = env('DB_CONNECTION', 'mysql_testing');
    if ($expectedConnection !== $defaultConnection) {
      throw new RuntimeException(sprintf(
        'Refusing to run tests using connection [%s]. Expected [%s]. Check phpunit.xml and .env.testing.',
        $defaultConnection,
        $expectedConnection
      ));
    }

    // 4) Optional guard: enforce that the testing database name matches expectation.
    $this->assertSame(
      'mysql_testing',
      config('database.default')
    );
    $this->assertSame(
      'mystorepanel_test',
      config('database.connections.mysql_testing.database')
    );
  }
}
