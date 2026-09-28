<?php

namespace App\Console\Commands;

use App\Services\Vft\VftApiException;
use App\Services\Vft\VftDeviceCommandService;
use Illuminate\Console\Command;

/**
 * Unlocks the door — a quick end-to-end test that commands reach the
 * physical device. Asks for confirmation in live mode.
 */
class VftOpenDoor extends Command
{
    protected $signature = 'vft:open-door {--device= : Device serial number (default: VFT_DEFAULT_DEVICE_SN)}';

    protected $description = 'Unlock the door on the device';

    public function handle(VftDeviceCommandService $commands): int
    {
        if ($commands->mode() === 'live' && ! $this->confirm('Unlock the door on the real device?')) {
            return self::SUCCESS;
        }

        try {
            $result = $commands->openDoor($this->option('device'));
        } catch (VftApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("{$result->describe()} on {$result->devSn} → ".($result->sent ? 'sent' : "not sent (VFT_MODE={$result->mode})"));
        if ($result->sent) {
            $this->line(json_encode($result->response, JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
