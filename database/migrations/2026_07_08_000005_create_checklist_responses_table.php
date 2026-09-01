<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('checklist_responses')) {
            Schema::create('checklist_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patrol_log_id')->constrained()->cascadeOnDelete();
                $table->boolean('area_secure')->default(false);
                $table->boolean('suspicious_activity')->default(false);
                $table->boolean('damaged_facility')->default(false);
                $table->boolean('lighting_ok')->default(false);
                $table->boolean('doors_locked')->default(false);
                $table->boolean('safety_hazard')->default(false);
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_responses');
    }
};
