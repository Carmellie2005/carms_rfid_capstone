<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('guard_face_descriptors', 'descriptor')) {
            return;
        }

        match (DB::connection()->getDriverName()) {
            'mysql' => DB::statement('ALTER TABLE guard_face_descriptors MODIFY descriptor JSON NULL'),
            'pgsql' => DB::statement('ALTER TABLE guard_face_descriptors ALTER COLUMN descriptor DROP NOT NULL'),
            default => null,
        };
    }

    public function down(): void
    {
        if (! Schema::hasColumn('guard_face_descriptors', 'descriptor')) {
            return;
        }

        DB::table('guard_face_descriptors')
            ->whereNull('descriptor')
            ->update(['descriptor' => json_encode([])]);

        match (DB::connection()->getDriverName()) {
            'mysql' => DB::statement('ALTER TABLE guard_face_descriptors MODIFY descriptor JSON NOT NULL'),
            'pgsql' => DB::statement('ALTER TABLE guard_face_descriptors ALTER COLUMN descriptor SET NOT NULL'),
            default => null,
        };
    }
};
