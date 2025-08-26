<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('tenants', function (Blueprint $table) {
      $table->unsignedInteger('user_seat_limit')
        ->default(2)
        ->after('status');
    });
  }

  public function down(): void {
    Schema::table('tenants', function (Blueprint $table) {
      $table->dropColumn('user_seat_limit');
    });
  }
};
