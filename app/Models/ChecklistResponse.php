<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'patrol_log_id',
        'area_secure',
        'suspicious_activity',
        'damaged_facility',
        'lighting_ok',
        'doors_locked',
        'safety_hazard',
        'perimeter_checked',
        'equipment_functional',
        'cctv_alarm_checked',
        'fire_exits_clear',
        'emergency_equipment_accessible',
        'no_unauthorized_person',
        'remarks',
    ];

    protected $casts = [
        'area_secure' => 'boolean',
        'suspicious_activity' => 'boolean',
        'damaged_facility' => 'boolean',
        'lighting_ok' => 'boolean',
        'doors_locked' => 'boolean',
        'safety_hazard' => 'boolean',
        'perimeter_checked' => 'boolean',
        'equipment_functional' => 'boolean',
        'cctv_alarm_checked' => 'boolean',
        'fire_exits_clear' => 'boolean',
        'emergency_equipment_accessible' => 'boolean',
        'no_unauthorized_person' => 'boolean',
    ];

    public function patrolLog(): BelongsTo
    {
        return $this->belongsTo(PatrolLog::class);
    }
}
