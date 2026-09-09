<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guard_face_descriptors', function (Blueprint $table) {
            $table->string('capture_type')->nullable()->after('image_path');
            $table->index(['guard_id', 'capture_type']);
        });
    }

    public function down(): void
    {
        Schema::table('guard_face_descriptors', function (Blueprint $table) {
            $table->dropIndex(['guard_id', 'capture_type']);
            $table->dropColumn('capture_type');
        });
    }
};
