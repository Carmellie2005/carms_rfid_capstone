<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class PatrolChecklist
{
    public const STATUS_NORMAL = 'normal';
    public const STATUS_ISSUE = 'issue';
    public const STATUS_NOT_APPLICABLE = 'na';

    public const ITEMS = [
        'doors_locked' => 'Doors, gates, and locks checked',
        'lighting_ok' => 'Lighting and visibility checked',
        'cctv_alarm_checked' => 'CCTV/security equipment area checked',
        'no_unauthorized_person' => 'No suspicious person, item, or vehicle observed',
        'safety_hazard' => 'No damage, obstruction, leak, or safety hazard observed',
        'area_secure' => 'Area condition recorded with photo proof',
    ];

    public const STATUS_OPTIONS = [
        self::STATUS_NORMAL => 'Normal',
        self::STATUS_ISSUE => 'Issue Found',
        self::STATUS_NOT_APPLICABLE => 'Not Applicable',
    ];

    public const INCIDENT_CATEGORIES = [
        'Suspicious Activity',
        'Unauthorized Entry / Trespassing',
        'Theft / Missing Item',
        'Property Damage / Vandalism',
        'Safety Hazard',
        'Facility Issue',
        'Disturbance / Conflict',
        'Lost and Found',
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

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function label(string $field): ?string
    {
        return self::ITEMS[$field] ?? null;
    }

    public static function incidentCategories(): array
    {
        return self::INCIDENT_CATEGORIES;
    }

    public static function validationRules(): array
    {
        return [
            'checklist_statuses' => ['required', 'array', 'size:'.count(self::fields())],
            ...collect(self::fields())
                ->mapWithKeys(fn (string $field) => [
                    "checklist_statuses.{$field}" => ['required', 'string', Rule::in(array_keys(self::STATUS_OPTIONS))],
                ])
                ->all(),
        ];
    }

    public static function valuesFromRequest(Request $request): array
    {
        $statuses = self::statusesFromRequest($request);

        return collect(self::fields())
            ->mapWithKeys(fn (string $field) => [$field => ($statuses[$field] ?? null) === self::STATUS_NORMAL])
            ->all();
    }

    public static function statusesFromRequest(Request $request): array
    {
        $statuses = $request->input('checklist_statuses', []);

        return collect(self::fields())
            ->mapWithKeys(fn (string $field) => [
                $field => self::normalizeStatus($statuses[$field] ?? null) ?? self::STATUS_NORMAL,
            ])
            ->all();
    }

    public static function issueFieldsFromRequest(Request $request): Collection
    {
        return collect(self::statusesFromRequest($request))
            ->filter(fn (string $status) => $status === self::STATUS_ISSUE)
            ->keys()
            ->values();
    }

    public static function statusLabel(?string $status): ?string
    {
        $status = self::normalizeStatus($status);

        return $status ? self::STATUS_OPTIONS[$status] : null;
    }

    public static function statusBadgeClasses(?string $status): string
    {
        return match (self::normalizeStatus($status)) {
            self::STATUS_ISSUE => 'bg-amber-50 text-amber-800 ring-amber-200',
            self::STATUS_NOT_APPLICABLE => 'bg-slate-50 text-slate-600 ring-slate-200',
            default => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        };
    }

    public static function statusSummaries(?object $checklist): Collection
    {
        if (! $checklist) {
            return collect();
        }

        $statuses = data_get($checklist, 'item_statuses');

        if (is_array($statuses) && $statuses !== []) {
            return collect(self::ITEMS)
                ->map(fn (string $label, string $field) => [
                    'field' => $field,
                    'label' => $label,
                    'status' => self::normalizeStatus($statuses[$field] ?? null) ?? self::STATUS_NORMAL,
                    'status_label' => self::statusLabel($statuses[$field] ?? null) ?? self::STATUS_OPTIONS[self::STATUS_NORMAL],
                ])
                ->values();
        }

        return collect(self::ITEMS)
            ->filter(fn (string $label, string $field) => (bool) data_get($checklist, $field))
            ->map(fn (string $label, string $field) => [
                'field' => $field,
                'label' => $label,
                'status' => self::STATUS_NORMAL,
                'status_label' => self::STATUS_OPTIONS[self::STATUS_NORMAL],
            ])
            ->values();
    }

    public static function checkedLabels(?object $checklist): Collection
    {
        return self::statusSummaries($checklist)
            ->map(fn (array $item) => "{$item['label']}: {$item['status_label']}");
    }

    private static function normalizeStatus(mixed $status): ?string
    {
        return is_string($status) && array_key_exists($status, self::STATUS_OPTIONS)
            ? $status
            : null;
    }
}
