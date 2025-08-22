<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Add `users.status` as an ENUM with an index.
   * Values: active | pending_invite | suspended | locked
   */
  public function up(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->enum('status', ['active', 'pending_invite', 'suspended', 'locked'])
        ->default('active')
        ->after('password');

      $table->index('status');
    });
  }

  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->dropIndex(['status']); // users_status_index
      $table->dropColumn('status');
    });
  }
};
