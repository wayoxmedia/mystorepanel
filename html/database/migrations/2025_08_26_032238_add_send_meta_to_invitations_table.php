<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('invitations', function (Blueprint $table) {
      // Cuándo se envió por última vez el correo de invitación
      $table->timestamp('last_sent_at')->nullable()->after('expires_at');
      // Contador total de envíos (creación + reenvíos)
      $table->unsignedInteger('send_count')->default(0)->after('last_sent_at');
    });
  }

  public function down(): void
  {
    Schema::table('invitations', function (Blueprint $table) {
      $table->dropColumn(['last_sent_at', 'send_count']);
    });
  }
};
