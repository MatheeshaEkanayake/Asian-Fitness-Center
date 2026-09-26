<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AttendanceController — handles listing and recording attendance.
 *
 * This controller replaces the static mock data and client-side
 * filtering logic in the React frontend:
 *
 *   Frontend file: src/data/mockData.js → initialAttendance
 *   Frontend page: src/pages/members/MemberAttendencePage.jsx
 *
 *   The useMemo filter/sort/pagination logic in MemberAttendencePage.jsx
 *   now becomes server-side query params on GET /api/attendance.
 *
 *   Endpoints:
 *   ┌────────────────────────┬──────────────────────────────────────────────┐
 *   │ Backend endpoint       │ Replaces                                     │
 *   ├────────────────────────┼──────────────────────────────────────────────┤
 *   │ GET  /api/attendance   │ Static initialAttendance array + useMemo()   │
 *   │ POST /api/attendance   │ (new) — no frontend equivalent, creates new  │
 *   │                        │ check-in records                             │
 *   └────────────────────────┴──────────────────────────────────────────────┘
 */
class AttendanceController extends Controller
{
    /**
     * GET /api/attendance
     *
     * List attendance records with filtering, sorting, and pagination.
     *
     * Query params (mirror MemberAttendencePage.jsx state):
     *   ?search={string}          — filters by member name, email, or date
     *   ?status={Present|Late|Absent}
     *   ?sort={date|name}         — default: date (newest first)
     *   ?per_page={int}           — default: 6 (matches PAGE_SIZE in frontend)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::query();

        // Status filter (mirrors statusFilter state in MemberAttendencePage.jsx)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Search by member name, email, or date (mirrors the query state in MemberAttendencePage.jsx)
        if ($search = $request->input('search')) {
            $q = strtolower($search);
            $query->where(function ($builder) use ($q) {
                $builder->whereRaw('LOWER(member_name) LIKE ?', ["%{$q}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$q}%"])
                        // NOTE: was 'CAST(date AS TEXT)' (SQLite-only syntax); 'AS CHAR'
                        // is the MySQL-compatible cast now that the app runs on MySQL.
                        ->orWhereRaw('CAST(date AS CHAR) LIKE ?', ["%{$q}%"]);
            });
        }

        // Sorting (mirrors sortBy state in MemberAttendencePage.jsx)
        $sort = $request->input('sort', 'date');
        if ($sort === 'name') {
            $query->orderBy('member_name');
        } else {
            $query->orderByDesc('date');
        }

        $records = $query->paginate($request->input('per_page', 6));

        return response()->json($records);
    }

    /**
     * POST /api/attendance
     *
     * Record a new attendance check-in (no frontend equivalent — new feature).
     * The frontend currently uses static mock data; this endpoint supports
     * real check-in recording once the frontend is connected.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'member_id'      => 'required|exists:members,id',
            'date'           => 'required|date',
            'check_in_time'  => 'nullable|string',
            'check_out_time' => 'nullable|string',
            'status'         => 'required|in:Present,Late,Absent',
        ]);

        // Denormalise member_name and email from the related member record
        $member = \App\Models\Member::findOrFail($data['member_id']);
        $data['member_name'] = $member->full_name;
        $data['email']       = $member->email;

        $record = Attendance::create($data);

        return response()->json($record, 201);
    }
}
