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
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('address', 100)->unique(); // Address of the subscriber
            $table->enum('address_type', ['p', 'e']); // Type of address (Phone, Email)
            $table->string('user_ip', 45)->nullable(); // User's IP address (IPv4 or IPv6)
            $table->boolean('active')->default(true); // Active status
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
