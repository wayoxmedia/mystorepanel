<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AddProfileFieldsToTenants
 *
 * Purpose:
 * Add optional profile/billing fields to the tenants table:
 *  - billing_email (nullable)
 *  - timezone (nullable)
 *  - locale (nullable)
 *  - plan (nullable)
 *  - trial_ends_at (nullable timestamp)
 *
 * Assumptions:
 * - These columns do not currently exist in the 'tenants' table.
 * - MySQL 8.x. Timestamps default to NULL when marked nullable.
 *
 * Notes:
 * - Columns are placed after phone -> billing_email -> timezone
 * -> locale -> plan -> trial_ends_at.
 */
return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('tenants', function (Blueprint $table): void {
      $table->string('billing_email', 191)
        ->nullable()
        ->after('user_seat_limit');
      $table->string('timezone', 64)
        ->nullable()
        ->after('billing_email');
      $table->string('locale', 10)
        ->nullable()
        ->after('timezone');
      $table->string('plan', 100)
        ->nullable()
        ->after('locale');
      $table->timestamp('trial_ends_at')
        ->nullable()
        ->after('plan');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('tenants', function (Blueprint $table): void {
      $table->dropColumn([
        'trial_ends_at',
        'plan',
        'locale',
        'timezone',
        'billing_email',
      ]);
    });
  }
};
