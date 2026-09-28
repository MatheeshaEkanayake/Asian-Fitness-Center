<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceEnrollment extends Model
{
    protected $fillable = [
        'vft_command_id', 'device_sn', 'pin', 'kind', 'finger_id',
        'status', 'return_code', 'requested_at', 'completed_at',
    ];

    protected $casts = [
        'finger_id' => 'integer',
        'return_code' => 'integer',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
