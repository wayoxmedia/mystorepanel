<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Invitations table for user onboarding via token (no passwords via email).
   * status: pending | accepted | expired | revoked
   */
  public function up(): void
  {
    Schema::create('invitations', function (Blueprint $table) {
      $table->id();
      $table->string('email')->index();
      $table->unsignedBigInteger('tenant_id')
        ->nullable()
        ->index();

      $table->unsignedBigInteger('role_id')
        ->nullable()
        ->index();

      $table->string('token', 128)
        ->unique();

      $table->timestamp('expires_at')
        ->nullable()
        ->index();
      $table->enum('status', ['pending', 'accepted', 'expired', 'revoked'])
        ->default('pending')
        ->index();

      $table->unsignedBigInteger('invited_by')
        ->nullable()
        ->index();
      $table->timestamps();

      $table->foreign('tenant_id')
        ->references('id')
        ->on('tenants')->nullOnDelete();

      $table->foreign('role_id')
        ->references('id')
        ->on('roles')
        ->nullOnDelete();

      $table->foreign('invited_by')
        ->references('id')
        ->on('users')
        ->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('invitations');
  }
};
