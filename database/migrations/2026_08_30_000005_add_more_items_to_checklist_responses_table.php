<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'perimeter_checked' => 'safety_hazard',
            'equipment_functional' => 'perimeter_checked',
            'cctv_alarm_checked' => 'equipment_functional',
            'fire_exits_clear' => 'cctv_alarm_checked',
            'emergency_equipment_accessible' => 'fire_exits_clear',
            'no_unauthorized_person' => 'emergency_equipment_accessible',
        ];

        foreach ($columns as $column => $after) {
            if (! Schema::hasColumn('checklist_responses', $column)) {
                Schema::table('checklist_responses', function (Blueprint $table) use ($column, $after) {
                    $table->boolean($column)->default(false)->after($after);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'no_unauthorized_person',
            'emergency_equipment_accessible',
            'fire_exits_clear',
            'cctv_alarm_checked',
            'equipment_functional',
            'perimeter_checked',
        ] as $column) {
            if (Schema::hasColumn('checklist_responses', $column)) {
                Schema::table('checklist_responses', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
