<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'patrol_log_id',
        'area_secure',
        'suspicious_activity',
        'damaged_facility',
        'doors_checked',
        'gates_checked',
        'locks_checked',
        'lighting_ok',
        'visibility_checked',
        'doors_locked',
        'safety_hazard',
        'perimeter_checked',
        'equipment_functional',
        'cctv_alarm_checked',
        'fire_exits_clear',
        'emergency_equipment_accessible',
        'no_unauthorized_person',
        'item_statuses',
        'remarks',
    ];

    protected $casts = [
        'area_secure' => 'boolean',
        'suspicious_activity' => 'boolean',
        'damaged_facility' => 'boolean',
        'doors_checked' => 'boolean',
        'gates_checked' => 'boolean',
        'locks_checked' => 'boolean',
        'lighting_ok' => 'boolean',
        'visibility_checked' => 'boolean',
        'doors_locked' => 'boolean',
        'safety_hazard' => 'boolean',
        'perimeter_checked' => 'boolean',
        'equipment_functional' => 'boolean',
        'cctv_alarm_checked' => 'boolean',
        'fire_exits_clear' => 'boolean',
        'emergency_equipment_accessible' => 'boolean',
        'no_unauthorized_person' => 'boolean',
        'item_statuses' => 'array',
    ];

    public function patrolLog(): BelongsTo
    {
        return $this->belongsTo(PatrolLog::class);
    }

    public function proofPhotos(): HasMany
    {
        return $this->hasMany(ChecklistProofPhoto::class)->orderBy('sort_order');
    }
}
