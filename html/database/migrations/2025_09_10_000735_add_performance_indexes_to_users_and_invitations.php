<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  /**
   * Adds non-unique, performance-oriented indexes:
   * - invitations(token)
   * - invitations(tenant_id, status, created_at)
   * - invitations(tenant_id, email)
   * - users(tenant_id, status)
   *
   * Notes:
   * - Names are explicit to avoid duplicates and ease maintenance.
   * - We guard every CREATE/DROP with information_schema checks, so this
   *   migration is idempotent-safe across environments.
   */
  public function up(): void
  {
    // invitations(token)
    if (! $this->indexExists('invitations', 'invitations_token_idx')) {
      DB::statement('CREATE INDEX invitations_token_idx ON invitations (token)');
    }

    // invitations(tenant_id, status, created_at) --> listing/filtering/pagination
    if (! $this->indexExists('invitations', 'invitations_tenant_status_created_idx')) {
      DB::statement('CREATE INDEX invitations_tenant_status_created_idx ON invitations (tenant_id, status, created_at)');
    }

    // invitations(tenant_id, email) --> fast lookups / duplicate checks per-tenant
    if (! $this->indexExists('invitations', 'invitations_tenant_email_idx')) {
      DB::statement('CREATE INDEX invitations_tenant_email_idx ON invitations (tenant_id, email)');
    }

    // users(tenant_id, status) --> seat counts / filtering active users per-tenant
    if (! $this->indexExists('users', 'users_tenant_status_idx')) {
      DB::statement('CREATE INDEX users_tenant_status_idx ON users (tenant_id, status)');
    }
  }

  public function down(): void
  {
    if ($this->indexExists('invitations', 'invitations_token_idx')) {
      DB::statement('DROP INDEX invitations_token_idx ON invitations');
    }

    if ($this->indexExists('invitations', 'invitations_tenant_status_created_idx')) {
      DB::statement('DROP INDEX invitations_tenant_status_created_idx ON invitations');
    }

    if ($this->indexExists('invitations', 'invitations_tenant_email_idx')) {
      DB::statement('DROP INDEX invitations_tenant_email_idx ON invitations');
    }

    if ($this->indexExists('users', 'users_tenant_status_idx')) {
      DB::statement('DROP INDEX users_tenant_status_idx ON users');
    }
  }

  /**
   * Checks if a named index exists on a given table in the current schema.
   */
  private function indexExists(string $table, string $index): bool
  {
    $schema = DB::getDatabaseName();

    $rows = DB::select(
      'SELECT COUNT(1) AS c
               FROM information_schema.statistics
              WHERE table_schema = ?
                AND table_name   = ?
                AND index_name   = ?
              LIMIT 1',
      [$schema, $table, $index]
    );

    return isset($rows[0]) && ((int) $rows[0]->c) > 0;
  }
};
