<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One raw punch from the door device (see App\Services\Vft\AttendanceImporter).
 * punched_at is device local time (config vft.timezone).
 */
class DevicePunch extends Model
{
    protected $fillable = [
        'device_sn',
        'pin',
        'punched_at',
        'event_type',
        'member_id',
        'user_id',
        'raw',
    ];

    protected $casts = [
        'punched_at' => 'datetime',
        'raw' => 'array',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
