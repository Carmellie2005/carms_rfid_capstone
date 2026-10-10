<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkpoints', function (Blueprint $table) {
            if (! Schema::hasColumn('checkpoints', 'reader_diagnostics')) {
                $table->text('reader_diagnostics')->nullable()->after('reader_last_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('checkpoints', function (Blueprint $table) {
            if (Schema::hasColumn('checkpoints', 'reader_diagnostics')) {
                $table->dropColumn('reader_diagnostics');
            }
        });
    }
};
