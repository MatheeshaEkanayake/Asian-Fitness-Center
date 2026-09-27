<?php

namespace App\Observers;

use App\Jobs\SyncMemberToDevice;
use App\Models\Member;

/**
 * Queues a door-device push when something the device cares about changes:
 * PIN (Member ID number), name, status or access dates.
 */
class MemberObserver
{
    private const DEVICE_FIELDS = [
        'member_id_number',
        'full_name',
        'status',
        'access_valid_from',
        'access_valid_until',
    ];

    public function created(Member $member): void
    {
        $this->queuePush($member);
    }

    public function updated(Member $member): void
    {
        if ($member->wasChanged(self::DEVICE_FIELDS)) {
            $this->queuePush($member);
        }
    }

    private function queuePush(Member $member): void
    {
        if (config('vft.mode') !== 'off') {
            SyncMemberToDevice::dispatch($member->id)->afterCommit();
        }
    }
}
