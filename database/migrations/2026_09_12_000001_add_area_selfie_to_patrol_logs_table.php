<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patrol_logs')) {
            return;
        }

        Schema::table('patrol_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('patrol_logs', 'area_selfie_path')) {
                $table->string('area_selfie_path')->nullable()->after('status');
            }

            if (! Schema::hasColumn('patrol_logs', 'area_selfie_mime_type')) {
                $table->string('area_selfie_mime_type')->nullable()->after('area_selfie_path');
            }

            if (! Schema::hasColumn('patrol_logs', 'area_selfie_image_data')) {
                $table->longText('area_selfie_image_data')->nullable()->after('area_selfie_mime_type');
            }

            if (! Schema::hasColumn('patrol_logs', 'area_selfie_captured_at')) {
                $table->timestamp('area_selfie_captured_at')->nullable()->after('area_selfie_image_data');
            }

            if (! Schema::hasColumn('patrol_logs', 'area_selfie_latitude')) {
                $table->decimal('area_selfie_latitude', 10, 7)->nullable()->after('area_selfie_captured_at');
            }

            if (! Schema::hasColumn('patrol_logs', 'area_selfie_longitude')) {
                $table->decimal('area_selfie_longitude', 10, 7)->nullable()->after('area_selfie_latitude');
            }

            if (! Schema::hasColumn('patrol_logs', 'area_selfie_accuracy')) {
                $table->decimal('area_selfie_accuracy', 8, 2)->nullable()->after('area_selfie_longitude');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('patrol_logs')) {
            return;
        }

        Schema::table('patrol_logs', function (Blueprint $table) {
            foreach ([
                'area_selfie_accuracy',
                'area_selfie_longitude',
                'area_selfie_latitude',
                'area_selfie_captured_at',
                'area_selfie_image_data',
                'area_selfie_mime_type',
                'area_selfie_path',
            ] as $column) {
                if (Schema::hasColumn('patrol_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
