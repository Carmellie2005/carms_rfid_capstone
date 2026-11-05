<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_report_images', function (Blueprint $table): void {
            if (! Schema::hasColumn('incident_report_images', 'incident_evidence_id')) {
                $table->foreignId('incident_evidence_id')
                    ->nullable()
                    ->after('incident_report_id')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('incident_report_images', function (Blueprint $table): void {
            if (Schema::hasColumn('incident_report_images', 'incident_evidence_id')) {
                $table->dropForeign(['incident_evidence_id']);
                $table->dropColumn('incident_evidence_id');
            }
        });
    }
};
