<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('checkpoints')) {
            Schema::create('checkpoints', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('location');
                $table->string('device_uid')->nullable()->unique();
                $table->string('status')->default('active');
                $table->text('description')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('checkpoints', function (Blueprint $table) {
            if (! Schema::hasColumn('checkpoints', 'code')) {
                $table->string('code')->nullable()->after('id');
            }

            if (! Schema::hasColumn('checkpoints', 'location')) {
                $table->string('location')->nullable()->after('name');
            }

            if (! Schema::hasColumn('checkpoints', 'device_uid')) {
                $table->string('device_uid')->nullable()->after('location');
            }

            if (! Schema::hasColumn('checkpoints', 'status')) {
                $table->string('status')->default('active')->after('device_uid');
            }

            if (! Schema::hasColumn('checkpoints', 'description')) {
                $table->text('description')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkpoints');
    }
};
