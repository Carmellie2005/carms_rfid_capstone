<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RfidEnrollmentScan extends Model
{
    protected $fillable = [
        'rfid_uid',
        'device_uid',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];
}
