<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patrol_logs')) {
            Schema::create('patrol_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('guard_id')->nullable()->constrained('guards')->nullOnDelete();
                $table->foreignId('checkpoint_id')->nullable()->constrained()->nullOnDelete();
                $table->string('rfid_uid');
                $table->string('checkpoint_code')->nullable();
                $table->string('rfid_status')->default('pending');
                $table->string('facial_status')->default('pending');
                $table->string('status')->default('pending');
                $table->timestamp('scanned_at');
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('patrol_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('patrol_logs', 'rfid_uid')) {
                $table->string('rfid_uid')->nullable()->after('checkpoint_id');
            }

            if (! Schema::hasColumn('patrol_logs', 'checkpoint_code')) {
                $table->string('checkpoint_code')->nullable()->after('rfid_uid');
            }

            if (! Schema::hasColumn('patrol_logs', 'rfid_status')) {
                $table->string('rfid_status')->default('pending')->after('checkpoint_code');
            }

            if (! Schema::hasColumn('patrol_logs', 'facial_status')) {
                $table->string('facial_status')->default('pending')->after('rfid_status');
            }

            if (! Schema::hasColumn('patrol_logs', 'status')) {
                $table->string('status')->default('pending')->after('facial_status');
            }

            if (! Schema::hasColumn('patrol_logs', 'notes')) {
                $table->text('notes')->nullable()->after('scanned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_logs');
    }
};
