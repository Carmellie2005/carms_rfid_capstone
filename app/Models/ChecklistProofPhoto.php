<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistProofPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_response_id',
        'patrol_log_id',
        'item_key',
        'item_label',
        'image_path',
        'original_name',
        'mime_type',
        'image_data',
        'sort_order',
    ];

    public function checklistResponse(): BelongsTo
    {
        return $this->belongsTo(ChecklistResponse::class);
    }

    public function patrolLog(): BelongsTo
    {
        return $this->belongsTo(PatrolLog::class);
    }
}
