<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_no',
        'name',
        'email',
        'phone',
        'rfid_uid',
        'shift',
        'status',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patrolLogs(): HasMany
    {
        return $this->hasMany(PatrolLog::class);
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class);
    }
}
