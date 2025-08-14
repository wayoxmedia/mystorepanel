<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Keep it nullable so existing rows don't break; your seeders can fill it.
            $table->foreignId('template_id')
                ->nullable()
                ->after('slug') // or after('slug'), adjust as you like
                ->constrained('templates')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });
    }
};
