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
    Schema::table('subscribers', function (Blueprint $table) {
      // Rename store_id to tenant_id for consistency
      $table->renameColumn('store_id', 'tenant_id');
      // Mark when/why a subscriber unsubscribed (One-Click / complaint / bounce / manual)
      $table->timestamp('unsubscribed_at')
        ->nullable()
        ->after('address');
      $table->string('unsubscribe_source', 32)
        ->nullable()
        ->after('unsubscribed_at');
      $table->json('unsubscribe_meta')
        ->nullable()
        ->after('unsubscribe_source');

      $table->unsignedSmallInteger('bounce_count')
        ->default(0)
        ->after('unsubscribe_meta');
      $table->timestamp('complained_at')
        ->nullable()
        ->after('bounce_count');

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

      $table->dropColumn([
        'unsubscribed_at',
        'unsubscribe_source',
        'unsubscribe_meta',
        'bounce_count',
        'complained_at',
      ]);
    });
  }
};
