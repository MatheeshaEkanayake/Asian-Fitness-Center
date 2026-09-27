<?php

namespace App\Observers;

use App\Jobs\SyncMemberToDevice;
use App\Models\User;

/**
 * Staff door access follows their staff account: granting a role puts them
 * on the device with no expiry, deactivating the account blocks them.
 */
class UserObserver
{
    public function created(User $user): void
    {
        $this->queuePush($user->member_id);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('member_id')) {
            // Re-linked: the previous registration loses staff access.
            $this->queuePush($user->getOriginal('member_id'));
            $this->queuePush($user->member_id);
        } elseif ($user->wasChanged('status')) {
            $this->queuePush($user->member_id);
        }
    }

    public function deleted(User $user): void
    {
        $this->queuePush($user->member_id);
    }

    private function queuePush(?int $memberId): void
    {
        if ($memberId && config('vft.mode') !== 'off') {
            SyncMemberToDevice::dispatch($memberId)->afterCommit();
        }
    }
}
