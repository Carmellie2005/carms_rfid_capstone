<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceVerificationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'patrol_log_id',
        'guard_id',
        'status',
        'match_distance',
        'match_threshold',
        'model_name',
        'captured_image_path',
        'captured_descriptor',
        'notes',
        'verified_at',
    ];

    protected $casts = [
        'captured_descriptor' => 'array',
        'match_distance' => 'decimal:6',
        'match_threshold' => 'decimal:6',
        'verified_at' => 'datetime',
    ];

    public function patrolLog(): BelongsTo
    {
        return $this->belongsTo(PatrolLog::class);
    }

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }
}
