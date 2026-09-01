<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE guard_face_descriptors MODIFY descriptor JSON NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE guard_face_descriptors SET descriptor = JSON_ARRAY() WHERE descriptor IS NULL');
        DB::statement('ALTER TABLE guard_face_descriptors MODIFY descriptor JSON NOT NULL');
    }
};
