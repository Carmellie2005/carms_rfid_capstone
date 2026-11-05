<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->cascadeOnDelete();
            $table->foreignId('incident_report_id')->nullable()->constrained('incident_reports')->nullOnDelete();
            $table->string('image_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->longText('image_data')->nullable();
            $table->timestamp('captured_at');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['guard_id', 'incident_report_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_evidence');
    }
};
