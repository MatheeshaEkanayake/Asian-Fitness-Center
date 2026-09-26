<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attendance Eloquent model.
 *
 * Replaces the static mock data in:
 *   Frontend: src/data/mockData.js → initialAttendance
 *   Frontend: src/pages/members/MemberAttendencePage.jsx
 *
 * All filtering, sorting, and pagination that was done client-side with
 * useMemo() in MemberAttendencePage.jsx now happens in:
 *   AttendanceController (app/Http/Controllers/Api/AttendanceController.php)
 *   → GET /api/attendance (supports ?search=, ?status=, ?sort=, ?page= query params)
 *
 * Status values: Present | Late | Absent
 *
 * Note: member_name and email are kept denormalised for quick display
 * without a join — this mirrors the original mockData shape.
 */
class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'member_id',
        'member_name',
        'email',
        'date',
        'check_in_time',
        'check_out_time',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * The member this attendance record belongs to.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
