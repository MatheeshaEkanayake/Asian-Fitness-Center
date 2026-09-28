<?php

namespace App\Console\Commands;

use App\Services\Vft\VftApiClient;
use App\Services\Vft\VftApiException;
use Illuminate\Console\Command;

/**
 * Read-only: lists commands VFT has queued for the device — the way to see
 * whether sync/enroll/door actions are waiting for the device to check in.
 */
class VftCommands extends Command
{
    protected $signature = 'vft:commands
        {--device= : Only this device serial number}
        {--limit=20 : How many (ignored with --device)}';

    protected $description = 'List commands queued in the VFT cloud for the device (read-only)';

    public function handle(VftApiClient $client): int
    {
        try {
            $commands = $this->option('device')
                ? $client->listDeviceCommands($this->option('device'))
                : $client->listCommands((int) $this->option('limit'));
        } catch (VftApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = is_array($commands) ? ($commands['data'] ?? $commands['rows'] ?? $commands) : [];

        if (! $rows) {
            $this->info('No queued commands.');

            return self::SUCCESS;
        }

        // The row format isn't documented, so print it as VFT sends it.
        $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
