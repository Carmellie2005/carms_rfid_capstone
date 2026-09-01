<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_verification_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrol_log_id')->nullable()->constrained('patrol_logs')->nullOnDelete();
            $table->foreignId('guard_id')->nullable()->constrained('guards')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->decimal('match_distance', 8, 6)->nullable();
            $table->decimal('match_threshold', 8, 6)->default(0.420000);
            $table->string('model_name')->default('face-api.js');
            $table->string('captured_image_path')->nullable();
            $table->json('captured_descriptor')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['guard_id', 'status']);
            $table->index(['patrol_log_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_verification_attempts');
    }
};
