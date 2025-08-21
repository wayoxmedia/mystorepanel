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
      $table->unsignedBigInteger('store_id')->nullable()->after('id');
      $table->string('user_ip', 45)->nullable();
      $table->json('geo_location')->nullable();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('contacts', function (Blueprint $table) {
      $table->dropColumn('store_id');
      $table->dropColumn('user_ip');
      $table->dropColumn('geo_location');
    });
  }
};
