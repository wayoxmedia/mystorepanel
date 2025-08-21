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
    Schema::create('sites', function (Blueprint $table) {
      $table->id();

      // Foreign keys
      $table->foreignId('tenant_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
      $table->foreignId('template_id')->constrained()->cascadeOnUpdate(); // do not cascade on delete

      // Domain or subdomain; store normalized without leading "www."
      $table->string('domain')->unique();

      // Optional site-level metadata (CDN url, toggles, etc.)
      $table->json('meta')->nullable();

      $table->timestamps();

      // If you often filter by tenant, an index helps:
      $table->index('tenant_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('sites');
  }
};
