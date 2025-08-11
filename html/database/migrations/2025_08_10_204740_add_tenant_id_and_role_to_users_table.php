<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add tenant_id and role to users.
     * - tenant_id is nullable to keep existing users valid during rollout.
     * - Foreign key uses nullOnDelete to avoid cascading user deletion when a tenant is removed.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'tenant_id')) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete()
                    ->after('id');
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')
                    ->default('editor') // owner|admin|editor|viewer (customize as you need)
                    ->after('password');
            }

            // Helpful index if you will query by tenant frequently
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
