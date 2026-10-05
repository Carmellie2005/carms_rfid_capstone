<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'doors_checked' => 'damaged_facility',
            'gates_checked' => 'doors_checked',
            'locks_checked' => 'gates_checked',
            'visibility_checked' => 'lighting_ok',
        ];

        foreach ($columns as $column => $after) {
            if (! Schema::hasColumn('checklist_responses', $column)) {
                Schema::table('checklist_responses', function (Blueprint $table) use ($column, $after): void {
                    $table->boolean($column)->default(false)->after($after);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'visibility_checked',
            'locks_checked',
            'gates_checked',
            'doors_checked',
        ] as $column) {
            if (Schema::hasColumn('checklist_responses', $column)) {
                Schema::table('checklist_responses', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
