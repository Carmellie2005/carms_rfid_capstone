<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardFaceDescriptor extends Model
{
    use HasFactory;

    protected $fillable = [
        'guard_id',
        'descriptor',
        'model_name',
        'image_path',
        'is_primary',
    ];

    protected $casts = [
        'descriptor' => 'array',
        'is_primary' => 'boolean',
    ];

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }
}
