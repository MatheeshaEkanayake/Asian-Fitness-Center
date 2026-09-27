<?php

namespace App\Console\Commands;

use App\Services\Vft\VftApiException;
use App\Services\Vft\VftDeviceCommandService;
use Illuminate\Console\Command;

/**
 * Unlocks a door for a few seconds — a quick end-to-end test that commands
 * reach the physical device. Asks for confirmation.
 */
class VftOpenDoor extends Command
{
    protected $signature = 'vft:open-door
        {door=1 : Door number}
        {--seconds=5 : How long to keep it unlocked}';

    protected $description = 'Unlock a door on the device for a few seconds';

    public function handle(VftDeviceCommandService $commands): int
    {
        $door = (int) $this->argument('door');
        $seconds = (int) $this->option('seconds');

        if ($commands->mode() === 'live'
            && ! $this->confirm("Unlock door {$door} for {$seconds}s on the real device?")) {
            return self::SUCCESS;
        }

        try {
            $result = $commands->openDoor($door, $seconds);
        } catch (VftApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("Command: {$result->content} → ".($result->sent ? 'sent' : "not sent (VFT_MODE={$result->mode})"));
        if ($result->sent) {
            $this->line(json_encode($result->response, JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
