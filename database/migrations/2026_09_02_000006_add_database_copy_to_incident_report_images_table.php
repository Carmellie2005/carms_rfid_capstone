<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_report_images', function (Blueprint $table) {
            if (! Schema::hasColumn('incident_report_images', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('original_name');
            }

            if (! Schema::hasColumn('incident_report_images', 'image_data')) {
                $table->longText('image_data')->nullable()->after('mime_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('incident_report_images', function (Blueprint $table) {
            if (Schema::hasColumn('incident_report_images', 'image_data')) {
                $table->dropColumn('image_data');
            }

            if (Schema::hasColumn('incident_report_images', 'mime_type')) {
                $table->dropColumn('mime_type');
            }
        });
    }
};
