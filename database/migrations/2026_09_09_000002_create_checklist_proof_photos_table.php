<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_proof_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patrol_log_id')->constrained()->cascadeOnDelete();
            $table->string('item_key');
            $table->string('item_label');
            $table->string('image_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->longText('image_data')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['checklist_response_id', 'item_key']);
            $table->index(['patrol_log_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_proof_photos');
    }
};
