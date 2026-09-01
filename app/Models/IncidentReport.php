<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class IncidentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'patrol_log_id',
        'guard_id',
        'checkpoint_id',
        'title',
        'incident_type',
        'category',
        'priority',
        'severity',
        'location',
        'incident_at',
        'occurred_at',
        'reported_at',
        'description',
        'image_path',
        'status',
        'admin_notes',
        'action_taken',
        'resolved_at',
    ];

    protected $casts = [
        'incident_at' => 'datetime',
        'occurred_at' => 'datetime',
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function patrolLog(): BelongsTo
    {
        return $this->belongsTo(PatrolLog::class);
    }

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(IncidentReportImage::class)->orderBy('sort_order');
    }

    public function notificationReads(): MorphMany
    {
        return $this->morphMany(NotificationRead::class, 'notifiable');
    }
}
