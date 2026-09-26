<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rfid_enrollment_scans', function (Blueprint $table) {
            $table->id();
            $table->string('rfid_uid', 100);
            $table->string('device_uid', 100)->nullable();
            $table->timestamp('captured_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfid_enrollment_scans');
    }
};
