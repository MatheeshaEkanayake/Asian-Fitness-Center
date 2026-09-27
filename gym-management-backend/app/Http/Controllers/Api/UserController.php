<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * UserController — Setup > Users staff-account CRUD.
 *
 * Staff accounts aren't created from scratch any more: everyone registers
 * on /signup, and store() grants one of those registrations a role (a user
 * row linked by member_id). The login, name and email stay on the member.
 *
 * All routes are gated behind ['auth:sanctum', 'permission:setup.manage']
 * (see routes/api.php). Frontend: src/pages/setup/UsersListPage.jsx,
 * UserFormPage.jsx (src/context/UsersContext.jsx, src/services/userService.js).
 */
class UserController extends Controller
{
    /**
     * GET /api/setup/users
     */
    public function index(): JsonResponse
    {
        return response()->json(
            User::with(['role', 'branch', 'member'])->orderBy('full_name')->paginate(50)
        );
    }

    /**
     * GET /api/setup/users/candidates
     *
     * Registrations that can be granted a role: signed up (have a username)
     * and not already staff.
     */
    public function candidates(): JsonResponse
    {
        return response()->json(
            Member::query()
                ->notStaff()
                ->whereNotNull('username')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'username', 'email', 'status', 'member_id_number'])
                ->makeHidden(['membership_type', 'payment_status', 'today_attendance_status', 'payment_plan'])
        );
    }

    /**
     * GET /api/setup/users/{id}
     */
    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('role', 'branch', 'member'));
    }

    /**
     * POST /api/setup/users — grant a registered member a staff role.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'Active';
        $pin = array_key_exists('member_id_number', $data) ? $data['member_id_number'] : false;
        unset($data['member_id_number']);

        $user = User::create($data);

        // The door PIN lives on their registration.
        if ($pin !== false) {
            $user->member->update(['member_id_number' => $pin]);
        }

        // They're staff now, not a member: end any member-area sessions so
        // their next sign-in lands in the management app.
        $user->member->tokens()->delete();

        // fresh() so the member-derived name/email are in the response.
        return response()->json($user->fresh()->load('role', 'branch', 'member'), 201);
    }

    /**
     * PUT /api/setup/users/{id}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        // A linked user's name, email and door PIN belong to their registration.
        if ($user->member_id) {
            unset($data['full_name'], $data['email']);
            if (array_key_exists('member_id_number', $data)) {
                $user->member->update(['member_id_number' => $data['member_id_number']]);
            }
        }
        unset($data['member_id_number']);

        $user->update($data);

        return response()->json($user->fresh()->load('role', 'branch', 'member'));
    }

    /**
     * POST /api/setup/users/{id}/reset-password
     *
     * Admin-initiated password reset — there is no email-token self-service
     * reset flow in this app (see AuthController's class doc).
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $password = Hash::make($request->validated('password'));

        // Linked staff sign in with their registration's password.
        if ($user->member) {
            $user->member->update(['password' => $password]);
            $user->member->tokens()->delete();
        } else {
            $user->update(['password' => $password]);
        }

        // Revoke existing sessions so the old password can no longer be used
        // to authenticate an already-issued token.
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reset.']);
    }

    /**
     * DELETE /api/setup/users/{id}
     *
     * Soft-deactivate a user by setting status to 'Inactive', mirroring
     * MemberController::destroy() — preserves the login_activities audit
     * trail rather than hard-deleting the account.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->update(['status' => 'Inactive']);
        $user->tokens()->delete();

        return response()->json($user->fresh()->load('role', 'branch', 'member'));
    }
}
