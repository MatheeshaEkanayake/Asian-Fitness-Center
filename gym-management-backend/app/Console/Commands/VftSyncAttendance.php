<?php

namespace App\Console\Commands;

use App\Services\Vft\AttendanceImporter;
use App\Services\Vft\TransactionParser;
use App\Services\Vft\VftApiException;
use App\Services\Vft\VftDeviceCommandService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pulls door punches from the device and records attendance, skipping
 * punches already imported. Scheduled every 15 minutes (routes/console.php);
 * only does anything when VFT_MODE=live.
 */
class VftSyncAttendance extends Command
{
    protected $signature = 'vft:sync-attendance';

    protected $description = 'Import door punches from the device into member and staff attendance';

    public function handle(
        VftDeviceCommandService $commands,
        TransactionParser $parser,
        AttendanceImporter $importer,
    ): int {
        if ($commands->mode() !== 'live') {
            $this->warn('VFT_MODE is not live — attendance sync skipped.');

            return self::SUCCESS;
        }

        try {
            $result = $commands->getTransactions();
        } catch (VftApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $punches = $parser->parse($result->response);

        if (! $punches) {
            // The response may only confirm the command was queued; the raw
            // reply is in the vft log so the format can be checked.
            Log::channel('vft')->warning('Attendance sync: no punches found in the transaction response.');
            $this->warn('No punches found in the response (see storage/logs/vft.log for the raw reply).');

            return self::SUCCESS;
        }

        $stats = $importer->import($result->devSn, $punches);

        $this->info(sprintf(
            'Punches: %d new, %d already imported. Attendance: %d member, %d staff. Unknown PIN: %d. Not counted (event type): %d.',
            $stats['new'], $stats['duplicate'], $stats['members'], $stats['staff'], $stats['unknown'], $stats['ignored'],
        ));

        return self::SUCCESS;
    }
}
