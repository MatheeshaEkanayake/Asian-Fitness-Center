<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\Membership\AccessValidityService;
use Illuminate\Console\Command;

/**
 * Backfills door access dates from each member's latest paid payment
 * (date + plan length). Sends nothing to the device by itself; if VFT is
 * on, changed members are queued for a device push as usual.
 */
class RecalculateMemberAccess extends Command
{
    protected $signature = 'members:recalculate-access
        {members?* : Member ids (default: all)}';

    protected $description = 'Set members\' door access dates from their latest paid payment and its plan';

    public function handle(AccessValidityService $validity): int
    {
        $ids = $this->argument('members');
        $rows = [];

        Member::query()
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->each(function (Member $member) use ($validity, &$rows) {
                [$from, $until] = $validity->recalculate($member);
                $rows[] = [$member->id, $member->full_name, $from ?? '—', $until ?? '—', $member->access_note ?? ''];
            });

        $this->table(['ID', 'Name', 'Valid from', 'Valid until', 'Note'], $rows);

        return self::SUCCESS;
    }
}
