<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * MemberController — handles all member CRUD operations.
 *
 * This controller replaces the following functions from the React frontend:
 *
 *   Frontend file: src/services/memberService.js
 *   ┌─────────────────────────────┬──────────────────────────────────────────┐
 *   │ Frontend function           │ Backend endpoint                         │
 *   ├─────────────────────────────┼──────────────────────────────────────────┤
 *   │ listMembers()               │ GET    /api/members                      │
 *   │ getMember(memberId)         │ GET    /api/members/{id}                 │
 *   │ createMember(input)         │ POST   /api/members                      │
 *   │ updateMember(id, updates)   │ PUT    /api/members/{id}                 │
 *   │ setMemberStatus(id, status) │ PATCH  /api/members/{id}/status          │
 *   │ deactivateMember(memberId)  │ DELETE /api/members/{id}                 │
 *   └─────────────────────────────┴──────────────────────────────────────────┘
 *
 *   Frontend context: src/context/MembersContext.jsx
 *   → addMember(), editMember(), deactivateMember(), getMemberById()
 *
 *   Frontend pages that consume this:
 *   → src/pages/members/MemberListPage.jsx     (GET /api/members)
 *   → src/pages/members/MemberFormPage.jsx     (POST, PUT)
 *   → src/pages/members/MemberDetailPage.jsx   (GET /{id}, DELETE /{id})
 */
class MemberController extends Controller
{
    /**
     * GET /api/members
     *
     * List all members, with optional search, status filter, and sort.
     * Replaces: memberService.js → listMembers() + the useMemo filter/sort logic
     * in MemberListPage.jsx.
     *
     * Query params:
     *   ?search={string}          — filters by name, email, or phone
     *   ?status={Active|Inactive|Suspended}
     *   ?sort={name|joinDate}     — default: name
     *   ?per_page={int}           — default: 50 (frontend paginates client-side)
     */
    public function index(Request $request): JsonResponse
    {
        // Eager-load so membershipType/paymentStatus/todayAttendanceStatus
        // (see Member::$appends) don't trigger a query per row.
        // Staff (registrations granted a role) aren't gym members, and
        // guests are listed separately (GuestController).
        $query = Member::query()->notStaff()->notGuest()->with(['latestPayment', 'todayAttendance']);

        // Search by name, email, or phone (mirrors MemberListPage.jsx search logic)
        if ($search = $request->input('search')) {
            $q = strtolower($search);
            $query->where(function ($builder) use ($q) {
                $builder->whereRaw('LOWER(full_name) LIKE ?', ["%{$q}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$q}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$q}%"]);
            });
        }

        // Status filter (mirrors MemberListPage.jsx statusFilter state)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sorting (mirrors MemberListPage.jsx sortBy state)
        $sort = $request->input('sort', 'name');
        if ($sort === 'joinDate') {
            $query->orderByDesc('join_date');
        } else {
            $query->orderBy('full_name');
        }

        $members = $query->paginate($request->input('per_page', 50));

        return response()->json($members);
    }

    /**
     * GET /api/members/{id}
     *
     * Fetch a single member by ID.
     * Replaces: memberService.js → getMember(memberId)
     */
    public function show(Member $member): JsonResponse
    {
        return response()->json($member);
    }

    /**
     * POST /api/members
     *
     * Create a new member profile.
     * Replaces: memberService.js → createMember(input)
     *
     * Validation rules are defined in StoreMemberRequest.
     * Default values (joinDate = today, status = Active) are set here,
     * matching the defaults in memberService.js createMember().
     */
    public function store(StoreMemberRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $member = Member::create([
            'join_date' => now()->toDateString(),
            'status'    => 'Active',
            ...$data,
        ]);

        return response()->json($member, 201);
    }

    /**
     * PUT /api/members/{id}
     *
     * Update an existing member's profile.
     * Replaces: memberService.js → updateMember(memberId, updates)
     *
     * Validation rules are defined in UpdateMemberRequest.
     */
    public function update(UpdateMemberRequest $request, Member $member): JsonResponse
    {
        $member->update($request->validated());

        return response()->json($member->fresh());
    }

    /**
     * PATCH /api/members/{id}/status
     *
     * Change a member's status (Active | Inactive | Suspended).
     * Replaces: memberService.js → setMemberStatus(memberId, status)
     */
    public function updateStatus(Request $request, Member $member): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:Active,Inactive,Suspended',
        ]);

        $member->update(['status' => $request->input('status')]);

        return response()->json($member->fresh());
    }

    /**
     * DELETE /api/members/{id}
     *
     * Soft-deactivate a member by setting status to 'Inactive'.
     * Replaces: memberService.js → deactivateMember(memberId)
     *
     * Members are NOT hard-deleted so their payment history stays intact.
     * This matches the comment in memberService.js:
     *   "Members are soft-deleted (status → Inactive) so linked payment
     *    history stays intact. See flow spec §3.3 / §5."
     */
    public function destroy(Member $member): JsonResponse
    {
        $member->update(['status' => 'Inactive']);

        return response()->json($member->fresh());
    }

    /**
     * GET /api/members/{id}/payments
     *
     * List all payments for a specific member, sorted by date descending.
     * NOTE: renamed from transactions()/`/transactions` to payments()/`/payments`
     * to match the Transaction → Payment model/table rename.
     * Replaces: paymentService.js → listTransactionsForMember(memberId)
     * Also used by: src/pages/members/MemberDetailPage.jsx (Payment history tab)
     */
    public function payments(Member $member): JsonResponse
    {
        return response()->json(
            $member->payments()->orderByDesc('date')->get()
        );
    }
}
