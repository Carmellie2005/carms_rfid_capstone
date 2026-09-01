<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PatrolLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'guard_id',
        'checkpoint_id',
        'rfid_uid',
        'checkpoint_code',
        'rfid_status',
        'facial_status',
        'status',
        'scanned_at',
        'notes',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }

    public function checklistResponse(): HasOne
    {
        return $this->hasOne(ChecklistResponse::class);
    }

    public function faceVerificationAttempts(): HasMany
    {
        return $this->hasMany(FaceVerificationAttempt::class);
    }

    public function incidentReport(): HasOne
    {
        return $this->hasOne(IncidentReport::class);
    }

    public function notificationReads(): MorphMany
    {
        return $this->morphMany(NotificationRead::class, 'notifiable');
    }
}
