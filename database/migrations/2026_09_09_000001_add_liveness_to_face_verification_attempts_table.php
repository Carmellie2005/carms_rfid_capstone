<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_verification_attempts', function (Blueprint $table) {
            $table->string('liveness_challenge')->nullable()->after('model_name');
            $table->timestamp('liveness_confirmed_at')->nullable()->after('liveness_challenge');
        });
    }

    public function down(): void
    {
        Schema::table('face_verification_attempts', function (Blueprint $table) {
            $table->dropColumn(['liveness_challenge', 'liveness_confirmed_at']);
        });
    }
};
