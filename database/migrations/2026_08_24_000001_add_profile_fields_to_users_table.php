<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone')->nullable()->after('email');
            });
        }

        if (! Schema::hasColumn('users', 'profile_photo_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('profile_photo_path')->nullable()->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'profile_photo_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('profile_photo_path');
            });
        }

        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
