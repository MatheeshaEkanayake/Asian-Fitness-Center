<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staff check-ins from the door device (Members › Staff Attendance).
 * Same shape as Attendance, keyed by staff account instead of member.
 */
class StaffAttendance extends Model
{
    protected $table = 'staff_attendance';

    protected $fillable = [
        'user_id',
        'staff_name',
        'date',
        'check_in_time',
        'check_out_time',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
