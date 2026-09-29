<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\Membership\MemberPurger;
use Illuminate\Console\Command;
use Throwable;

/**
 * Permanently deletes members archived or not Active for 6+ months (see
 * MemberPurger). Scheduled daily in routes/console.php.
 */
class PurgeArchivedMembers extends Command
{
    protected $signature = 'members:purge-archived
        {--dry-run : List who would be deleted without deleting}';

    protected $description = 'Permanently delete members archived or inactive for 6+ months (payments and attendance are kept)';

    public function handle(MemberPurger $purger): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $failed = 0;
        $rows = [];

        $purger->due()->each(function (Member $member) use ($purger, $dryRun, &$failed, &$rows) {
            $since = $member->deleted_at ? 'archived '.$member->deleted_at->toDateString() : 'inactive '.$member->inactive_since?->toDateString();

            if ($dryRun) {
                $rows[] = [$member->id, $member->full_name, $member->status, $since, 'would delete'];

                return;
            }

            try {
                $purger->purge($member);
                $rows[] = [$member->id, $member->full_name, $member->status, $since, 'deleted'];
            } catch (Throwable $e) {
                $failed++;
                $rows[] = [$member->id, $member->full_name, $member->status, $since, 'failed: '.mb_substr($e->getMessage(), 0, 60)];
                report($e);
            }
        });

        if (! $rows) {
            $this->info('No members due for purging.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Status', 'Since', 'Result'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
