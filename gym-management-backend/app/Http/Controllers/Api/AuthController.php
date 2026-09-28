<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SignupRequest;
use App\Models\LoginActivity;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * AuthController — Sanctum token auth (login/logout/me).
 *
 * Token mode, not cookie/SPA mode: the frontend sends
 * `Authorization: Bearer <token>` (see src/services/apiClient.js), matching
 * the simplest setup for a single-origin-pair app with a small staff list.
 *
 * Everyone registers through signup() (a member with a username/password).
 * Staff access is granted afterwards by an admin in Setup > Users, which
 * links a `users` row to that registration (see UserController::store).
 * There is no email-token password reset — resets are admin-initiated
 * (UserController::resetPassword).
 */
class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     *
     * One sign-in for everyone, by username + password from their
     * registration (/signup):
     *   - a registration an admin has granted a role to (a linked `users`
     *     row) signs in as staff → token on the User, so permissions apply;
     *   - anyone else signs in as a member → token on the Member.
     * Unlinked staff accounts (the seeded bootstrap admin) type their email
     * into the same field instead.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $identifier = $request->validated('username');
        $password = $request->validated('password');

        $member = Member::with('staffAccount')->where('username', $identifier)->first();

        if ($member && $member->password && Hash::check($password, $member->password)) {
            if ($staff = $member->staffAccount) {
                return $this->staffLogin($request, $staff);
            }

            // Guests (registered, not yet made a member) can sign in too.
            if (! in_array($member->status, ['Active', 'Guest'], true)) {
                $this->fail('This membership is inactive. Please contact the front desk.');
            }

            return response()->json([
                'token' => $member->createToken('member-app')->plainTextToken,
                'user'  => $this->memberPayload($member),
            ]);
        }

        $user = User::whereNull('member_id')->where('email', $identifier)->first();

        if ($user && $user->password && Hash::check($password, $user->password)) {
            return $this->staffLogin($request, $user);
        }

        $this->fail('These credentials do not match our records.');
    }

    /**
     * POST /api/auth/signup
     *
     * Public self-signup. Everyone registers as a Guest and is signed
     * straight in; staff make them a member later (GuestController::promote),
     * which resets join_date to that day.
     */
    public function signup(SignupRequest $request): JsonResponse
    {
        $member = Member::create([
            ...$request->validated(),
            'join_date' => now()->toDateString(),
            'status'    => 'Guest',
        ]);

        return response()->json([
            'token' => $member->createToken('member-app')->plainTextToken,
            'user'  => $this->memberPayload($member),
        ], 201);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * GET /api/auth/me
     *
     * Re-derives the current user's role/permissions server-side on every
     * call — the frontend never caches permissions client-side, only the
     * bearer token (see src/context/AuthContext.jsx).
     */
    public function me(Request $request): JsonResponse
    {
        $account = $request->user();

        return response()->json(
            $account instanceof Member ? $this->memberPayload($account) : $this->userPayload($account)
        );
    }

    private function staffLogin(Request $request, User $user): JsonResponse
    {
        if ($user->status !== 'Active') {
            $this->fail('This staff account is inactive. Contact an administrator.');
        }

        $token = $user->createToken('gym-app')->plainTextToken;

        LoginActivity::create([
            'user_id'      => $user->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'logged_in_at' => now(),
        ]);

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['username' => [$message]]);
    }

    private function memberPayload(Member $member): array
    {
        return [
            'id'           => $member->id,
            'account_type' => 'member',
            'full_name'    => $member->full_name,
            'username'     => $member->username,
            'email'        => $member->email,
            'status'       => $member->status,
            'payment_plan' => $member->paymentPlan?->name,
            'is_admin'     => false,
            'permissions'  => [],
        ];
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing('role', 'branch', 'member');

        return [
            'id'           => $user->id,
            'account_type' => 'staff',
            'full_name'   => $user->full_name,
            'username'    => $user->username,
            'email'       => $user->email,
            'status'      => $user->status,
            'role'        => $user->role?->name,
            'branch'      => $user->branch?->name,
            'is_admin'    => $user->isAdmin(),
            'permissions' => $user->permissions(),
        ];
    }
}
