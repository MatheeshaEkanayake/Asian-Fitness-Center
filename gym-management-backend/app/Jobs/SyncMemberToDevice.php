<?php

namespace App\Jobs;

use App\Models\Member;
use App\Services\Vft\MemberDeviceSync;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushes one member's (or staff registration's) door access to the device
 * in the background, so saving a member/payment never waits on VFT.
 *
 * Unique per member while waiting in the queue: several changes in a row
 * collapse into one push, which sends the full state as it is when the job
 * runs. The lock is released when the job starts, so a change made during
 * a push queues a fresh one instead of being lost.
 * Dispatched by the Member/Payment/User observers when VFT_MODE isn't off.
 */
class SyncMemberToDevice implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 3;

    /** Seconds between retries (VFT or the network may be briefly down). */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $memberId) {}

    public function uniqueId(): string
    {
        return (string) $this->memberId;
    }

    public function handle(MemberDeviceSync $sync): void
    {
        $member = Member::find($this->memberId);

        if ($member) {
            $sync->sync($member);
        }
    }
}
