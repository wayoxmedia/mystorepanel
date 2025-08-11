<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            // Slug unique per tenant. Use "/" for home.
            $table->string('slug');           // e.g., "/", "about", "products/salsa-xyz"
            $table->string('title');          // SEO/page title
            $table->json('content')->nullable();  // Block-based content (hero, features, gallery, ...)

            // Optional SEO/meta fields
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->timestamps();

            // Uniqueness within tenant
            $table->unique(['tenant_id', 'slug'], 'uniq_page_tenant_slug');

            // Helpful indexes
            $table->index('tenant_id');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
