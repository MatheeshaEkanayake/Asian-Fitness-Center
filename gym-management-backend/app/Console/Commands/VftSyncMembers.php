<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\Vft\MemberDeviceSync;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pushes door access for some or all members/staff right now (not queued).
 * Use once after going live to load everyone onto the device.
 */
class VftSyncMembers extends Command
{
    protected $signature = 'vft:sync-members
        {members?* : Member ids (default: everyone with a Member ID number or already on the device)}';

    protected $description = 'Push members\' and staff door access to the device now';

    public function handle(MemberDeviceSync $sync): int
    {
        if (config('vft.mode') === 'off') {
            $this->warn('VFT_MODE=off — nothing to do. Set VFT_MODE=log (dry run) or live.');

            return self::SUCCESS;
        }

        $ids = $this->argument('members');
        $members = Member::query()
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->when(! $ids, fn ($q) => $q->where(fn ($q) => $q
                ->whereNotNull('member_id_number')
                ->orWhereNotNull('device_pin_synced')))
            ->orderBy('id')
            ->get();

        $failed = 0;
        $rows = [];

        foreach ($members as $member) {
            try {
                $status = $sync->sync($member);
                $rows[] = [$member->id, $member->full_name, $member->member_id_number, $status, ''];
            } catch (Throwable $e) {
                $failed++;
                $rows[] = [$member->id, $member->full_name, $member->member_id_number, 'failed', mb_substr($e->getMessage(), 0, 80)];
            }
        }

        $this->table(['ID', 'Name', 'PIN', 'Result', 'Error'], $rows);
        $this->line('Mode: '.config('vft.mode').'. Details in storage/logs/vft.log.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
