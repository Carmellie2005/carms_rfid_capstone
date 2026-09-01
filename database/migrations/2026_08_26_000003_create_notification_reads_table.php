<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_reads')) {
            return;
        }

        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['user_id', 'notifiable_type', 'notifiable_id'], 'notification_reads_unique');
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
    }
};
