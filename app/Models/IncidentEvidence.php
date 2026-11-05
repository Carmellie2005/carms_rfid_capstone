<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentEvidence extends Model
{
    use HasFactory;

    protected $table = 'incident_evidence';

    protected $fillable = [
        'guard_id',
        'incident_report_id',
        'image_path',
        'original_name',
        'mime_type',
        'image_data',
        'captured_at',
        'latitude',
        'longitude',
        'accuracy',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
    ];

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class);
    }

    public function incidentReport(): BelongsTo
    {
        return $this->belongsTo(IncidentReport::class);
    }
}
