<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkpoints', function (Blueprint $table) {
            if (! Schema::hasColumn('checkpoints', 'reader_last_seen_at')) {
                $table->timestamp('reader_last_seen_at')->nullable()->after('description');
            }

            if (! Schema::hasColumn('checkpoints', 'reader_last_ip')) {
                $table->string('reader_last_ip', 45)->nullable()->after('reader_last_seen_at');
            }

            if (! Schema::hasColumn('checkpoints', 'reader_last_status')) {
                $table->string('reader_last_status', 50)->nullable()->after('reader_last_ip');
            }

            if (! Schema::hasColumn('checkpoints', 'reader_last_message')) {
                $table->string('reader_last_message')->nullable()->after('reader_last_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('checkpoints', function (Blueprint $table) {
            foreach (['reader_last_message', 'reader_last_status', 'reader_last_ip', 'reader_last_seen_at'] as $column) {
                if (Schema::hasColumn('checkpoints', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
