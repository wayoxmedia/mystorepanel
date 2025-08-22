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
    Schema::create('theme_settings', function (Blueprint $table) {
      $table->id();
      $table->foreignId('tenant_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
      $table->foreignId('template_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

      $table->string('key');     // e.g., "branding", "header", "home.sections"
      $table->json('value')->nullable();

      $table->timestamps();

      // Ensure uniqueness of a given key within (tenant, template)
      $table->unique(['tenant_id', 'template_id', 'key'], 'uniq_theme_setting_scope');

      // Helpful indexes
      $table->index(['tenant_id', 'template_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('theme_settings');
  }
};
