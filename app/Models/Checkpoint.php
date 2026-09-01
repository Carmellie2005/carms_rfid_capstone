<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Checkpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'location',
        'device_uid',
        'status',
        'description',
        'reader_last_seen_at',
        'reader_last_ip',
        'reader_last_status',
        'reader_last_message',
    ];

    protected $casts = [
        'reader_last_seen_at' => 'datetime',
    ];

    public function patrolLogs(): HasMany
    {
        return $this->hasMany(PatrolLog::class);
    }

    public function latestPatrolLog(): HasOne
    {
        return $this->hasOne(PatrolLog::class)->latestOfMany('scanned_at');
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class);
    }
}
