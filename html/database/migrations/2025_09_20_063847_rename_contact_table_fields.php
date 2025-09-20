<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('contacts', function (Blueprint $table) {
      // Rename store_id to tenant_id for consistency
      $table->renameColumn('store_id', 'tenant_id');

      $table->foreign('tenant_id')
        ->references('id')
        ->on('tenants')
        ->cascadeOnUpdate()
        ->cascadeOnDelete();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('subscribers', function (Blueprint $table) {
      $table->dropForeign(['tenant_id']);
    });
  }
};
