<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * GuestController — Members > Guests.
 *
 * Everyone who signs up (AuthController::signup) is a Guest: a `members` row
 * with status 'Guest'. Staff make them a member here by picking a plan.
 * Registrations granted a staff role are left out (Setup > Users handles
 * those).
 *
 *   Frontend: src/services/guestService.js, src/pages/members/Guest*.jsx
 *   ┌──────────────────────────┬──────────────────────────────────────────┐
 *   │ listGuests()             │ GET    /api/guests                       │
 *   │ getGuest(id)             │ GET    /api/guests/{id}                  │
 *   │ promoteGuest(id, input)  │ POST   /api/guests/{id}/promote          │
 *   │ deleteGuest(id)          │ DELETE /api/guests/{id}                  │
 *   └──────────────────────────┴──────────────────────────────────────────┘
 */
class GuestController extends Controller
{
    /**
     * GET /api/guests — newest registrations first.
     *
     * ?search={string} — name, username, phone or WhatsApp number
     */
    public function index(Request $request): JsonResponse
    {
        $query = Member::query()->guests()->notStaff()->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $q = '%'.strtolower($search).'%';
            $query->where(function ($builder) use ($q) {
                $builder->whereRaw('LOWER(full_name) LIKE ?', [$q])
                        ->orWhereRaw('LOWER(username) LIKE ?', [$q])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$q])
                        ->orWhereRaw('LOWER(whatsapp_number) LIKE ?', [$q]);
            });
        }

        return response()->json($query->get());
    }

    /** GET /api/guests/{member} */
    public function show(Member $member): JsonResponse
    {
        $this->ensureGuest($member);

        return response()->json($member);
    }

    /**
     * POST /api/guests/{member}/promote
     *
     * Makes a guest an Active member on the chosen plan, joining today. The
     * door PIN is optional here — door access starts once a payment is
     * recorded (AccessValidityService) and they have a PIN.
     */
    public function promote(Request $request, Member $member): JsonResponse
    {
        $this->ensureGuest($member);

        $data = $request->validate([
            'payment_plan_id'  => ['required', Rule::exists('payment_plans', 'id')->where('is_active', true)],
            'member_id_number' => ['nullable', 'regex:/^\d{1,9}$/', Rule::unique('members', 'member_id_number')->ignore($member->id)],
        ], [
            'payment_plan_id.required' => 'Select a payment plan.',
            'payment_plan_id.exists'   => 'That payment plan is no longer available.',
            'member_id_number.regex'   => 'Member ID number must be 1–9 digits (it is the door PIN).',
            'member_id_number.unique'  => 'That Member ID number is already used by someone else.',
        ]);

        $member->update([
            'payment_plan_id'  => $data['payment_plan_id'],
            'member_id_number' => $data['member_id_number'] ?? $member->member_id_number,
            'status'           => 'Active',
            'join_date'        => now()->toDateString(),
        ]);

        return response()->json($member->fresh());
    }

    /**
     * DELETE /api/guests/{member}
     *
     * Permanently removes a guest registration (spam, duplicates). Guests
     * can't have payments, so no history is lost.
     */
    public function destroy(Member $member): JsonResponse
    {
        $this->ensureGuest($member);

        DB::transaction(function () use ($member) {
            $member->tokens()->delete();
            $member->delete();
        });

        return response()->json(['message' => 'Guest deleted.']);
    }

    /** 404 for anyone who isn't a guest, so these routes can't touch members or staff. */
    private function ensureGuest(Member $member): void
    {
        abort_unless($member->isGuest() && ! $member->staffAccount()->exists(), 404);
    }
}
