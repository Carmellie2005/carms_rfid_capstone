<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentReportImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_report_id',
        'image_path',
        'original_name',
        'mime_type',
        'image_data',
        'source',
        'sort_order',
    ];

    public function incidentReport(): BelongsTo
    {
        return $this->belongsTo(IncidentReport::class);
    }
}
