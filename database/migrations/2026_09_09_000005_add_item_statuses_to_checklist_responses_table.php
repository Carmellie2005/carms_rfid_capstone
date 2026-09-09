<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('checklist_responses', 'item_statuses')) {
            Schema::table('checklist_responses', function (Blueprint $table) {
                $table->json('item_statuses')->nullable()->after('no_unauthorized_person');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('checklist_responses', 'item_statuses')) {
            Schema::table('checklist_responses', function (Blueprint $table) {
                $table->dropColumn('item_statuses');
            });
        }
    }
};
