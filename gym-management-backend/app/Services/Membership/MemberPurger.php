<?php

namespace App\Services\Membership;

use App\Models\Member;
use App\Services\Vft\VftDeviceCommandService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Permanently removes members who have been archived (deleted from the
 * member list) or not Active for 6+ months. Staff, guests and Active
 * members are never touched.
 *
 * Their payments and attendance are kept: payments get a copy of the
 * member's name / Member ID number / phone so payment details still show
 * who paid, and member_id on both is cleared by the database.
 */
class MemberPurger
{
    public const MONTHS = 6;

    public function __construct(private readonly VftDeviceCommandService $commands) {}

    /** Members due for purging. */
    public function due(): Builder
    {
        $cutoff = now()->subMonths(self::MONTHS);

        return Member::withTrashed()
            ->notStaff()
            ->whereNotIn('status', ['Active', 'Guest'])
            ->where(fn ($q) => $q
                ->where('deleted_at', '<=', $cutoff)
                ->orWhere('inactive_since', '<=', $cutoff))
            ->orderBy('id');
    }

    public function purge(Member $member): void
    {
        // Off the door device first; if that fails the member is kept and
        // tried again on the next run rather than left on the device.
        if ($member->device_pin_synced && $this->commands->mode() !== 'off') {
            $this->commands->removePerson($member->device_pin_synced);
        }

        DB::transaction(function () use ($member) {
            $member->payments()->update([
                'member_name' => $member->full_name,
                'member_id_number' => $member->member_id_number,
                'member_phone' => $member->phone,
            ]);
            $member->tokens()->delete();
            $member->forceDelete();
        });
    }
}
