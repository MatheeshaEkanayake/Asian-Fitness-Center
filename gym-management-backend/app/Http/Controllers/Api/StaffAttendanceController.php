<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * StaffAttendanceController — Members › Staff Attendance.
 *
 * Staff check-ins recorded from the door device by
 * App\Services\Vft\AttendanceImporter (staff aren't members, so they're
 * kept out of the member attendance list). Read-only.
 *
 * Frontend: src/pages/members/StaffAttendancePage.jsx
 */
class StaffAttendanceController extends Controller
{
    /**
     * GET /api/staff-attendance
     *
     *   ?search={string}  — staff name or date
     *   ?sort={date|name} — default: date (newest first)
     *   ?per_page={int}   — default: 6
     */
    public function index(Request $request): JsonResponse
    {
        $query = StaffAttendance::query();

        if ($search = $request->input('search')) {
            $q = strtolower($search);
            $query->where(function ($builder) use ($q) {
                $builder->whereRaw('LOWER(staff_name) LIKE ?', ["%{$q}%"])
                        ->orWhereRaw('CAST(date AS CHAR) LIKE ?', ["%{$q}%"]);
            });
        }

        $request->input('sort') === 'name'
            ? $query->orderBy('staff_name')
            : $query->orderByDesc('date')->orderBy('staff_name');

        return response()->json($query->paginate($request->input('per_page', 6)));
    }
}
