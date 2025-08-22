<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Minimal audit trail for critical actions (invites, role changes, impersonation).
   */
  public function up(): void
  {
    Schema::create('audit_logs', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('actor_id')
        ->nullable()
        ->index();   // user who performed the action

      $table->string('action'); // e.g., user.invited, invite.accepted

      $table->string('subject_type')->nullable(); // e.g., App\Models\User

      $table->unsignedBigInteger('subject_id')
        ->nullable()
        ->index(); // affected entity id

      $table->json('meta')->nullable();

      $table->timestamps();

      $table->foreign('actor_id')
        ->references('id')
        ->on('users')->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('audit_logs');
  }
};
