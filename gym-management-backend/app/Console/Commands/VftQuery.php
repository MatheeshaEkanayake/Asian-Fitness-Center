<?php

namespace App\Console\Commands;

use App\Services\Vft\VftApiException;
use App\Services\Vft\VftDeviceCommandService;
use Illuminate\Console\Command;

/**
 * Sends one read-only DATA QUERY to the device and prints VFT's raw reply —
 * the way to find out what /api/devicecmd actually returns.
 */
class VftQuery extends Command
{
    protected $signature = 'vft:query
        {table : users | fingerprints | transactions}
        {--live : Send even if VFT_MODE is not live}';

    protected $description = 'Query the door device (users, fingerprints or transactions) and print the raw VFT response';

    public function handle(VftDeviceCommandService $commands): int
    {
        $method = match ($this->argument('table')) {
            'users' => 'getUsers',
            'fingerprints' => 'getFingerprintTemplates',
            'transactions' => 'getTransactions',
            default => null,
        };

        if (! $method) {
            $this->error('Table must be users, fingerprints or transactions.');

            return self::INVALID;
        }

        if ($this->option('live')) {
            config(['vft.mode' => 'live']);
        }

        try {
            $result = $commands->{$method}();
        } catch (VftApiException $e) {
            $this->error($e->getMessage());
            $this->line((string) $e->responseBody);

            return self::FAILURE;
        }

        $this->line("Device: {$result->devSn}");
        $this->line('Command: '.str_replace("\t", '\t', $result->content));

        if (! $result->sent) {
            $this->warn("Not sent (VFT_MODE={$result->mode}). Use --live to send this query anyway.");

            return self::SUCCESS;
        }

        $this->info('Raw response (also in storage/logs/vft.log):');
        $this->line(is_string($result->response)
            ? $result->response
            : json_encode($result->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
