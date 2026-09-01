<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guard_face_descriptors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->cascadeOnDelete();
            $table->json('descriptor')->nullable();
            $table->string('model_name')->default('face-api.js');
            $table->string('image_path')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['guard_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guard_face_descriptors');
    }
};
