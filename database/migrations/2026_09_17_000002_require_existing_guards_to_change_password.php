<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'role')
            || ! Schema::hasColumn('users', 'must_change_password')) {
            return;
        }

        DB::table('users')
            ->where('role', 'guard')
            ->update([
                'must_change_password' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'role')
            || ! Schema::hasColumn('users', 'must_change_password')) {
            return;
        }

        DB::table('users')
            ->where('role', 'guard')
            ->update([
                'must_change_password' => false,
                'updated_at' => now(),
            ]);
    }
};
