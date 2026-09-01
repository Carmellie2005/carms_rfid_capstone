<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PatrolChecklist
{
    public const ITEMS = [
        'area_secure' => 'Area secure',
        'lighting_ok' => 'Lighting OK',
        'doors_locked' => 'Doors/windows locked',
        'perimeter_checked' => 'Perimeter checked',
        'equipment_functional' => 'Equipment functional',
        'cctv_alarm_checked' => 'CCTV/alarm checked',
        'fire_exits_clear' => 'Fire exits clear',
        'emergency_equipment_accessible' => 'Emergency equipment accessible',
        'no_unauthorized_person' => 'No unauthorized persons',
        'suspicious_activity' => 'Suspicious activity',
        'damaged_facility' => 'Damaged facility',
        'safety_hazard' => 'Safety hazard',
    ];

    public const INCIDENT_CATEGORIES = [
        'Suspicious Activity',
        'Emergency',
        'Safety Hazard',
        'Damaged Facility',
        'Unauthorized Access',
        'Medical Emergency',
        'Fire or Smoke',
        'Theft or Robbery',
        'Vandalism',
        'Violence or Altercation',
        'Alarm or CCTV Issue',
        'Vehicle Concern',
        'Maintenance Concern',
        'Lost Item',
        'Other',
    ];

    public static function items(): array
    {
        return self::ITEMS;
    }

    public static function fields(): array
    {
        return array_keys(self::ITEMS);
    }

    public static function incidentCategories(): array
    {
        return self::INCIDENT_CATEGORIES;
    }

    public static function validationRules(): array
    {
        return collect(self::fields())
            ->mapWithKeys(fn (string $field) => [$field => ['nullable', 'boolean']])
            ->all();
    }

    public static function valuesFromRequest(Request $request): array
    {
        return collect(self::fields())
            ->mapWithKeys(fn (string $field) => [$field => $request->boolean($field)])
            ->all();
    }

    public static function checkedLabels(?object $checklist): Collection
    {
        if (! $checklist) {
            return collect();
        }

        return collect(self::ITEMS)
            ->filter(fn (string $label, string $field) => (bool) data_get($checklist, $field))
            ->values();
    }
}
