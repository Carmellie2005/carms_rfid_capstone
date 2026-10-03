<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patrol_logs') || ! Schema::hasColumn('patrol_logs', 'facial_status')) {
            return;
        }

        Schema::table('patrol_logs', function (Blueprint $table) {
            $table->dropColumn('facial_status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('patrol_logs') || Schema::hasColumn('patrol_logs', 'facial_status')) {
            return;
        }

        Schema::table('patrol_logs', function (Blueprint $table) {
            $table->string('facial_status')->default('not_required');
        });
    }
};
