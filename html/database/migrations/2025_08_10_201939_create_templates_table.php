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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            // Basic info
            $table->string('slug')->unique();   // e.g., "default", "modern"
            $table->string('name');
            $table->boolean('is_active')->default(true);

            // Optional metadata
            $table->string('version')->nullable();
            $table->text('description')->nullable();
            $table->text('img_url')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
