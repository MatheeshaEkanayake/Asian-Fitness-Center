<?php

namespace App\Console\Commands;

use App\Services\Vft\VftApiClient;
use App\Services\Vft\VftApiException;
use Illuminate\Console\Command;

/**
 * Read-only health check: signs in to VFT and lists devices, areas and
 * employees. Sends nothing to the device.
 */
class VftCheck extends Command
{
    protected $signature = 'vft:check';

    protected $description = 'Sign in to the VFT cloud and list devices, areas and employees (read-only)';

    public function handle(VftApiClient $client): int
    {
        $defaultSn = config('vft.default_device_sn');

        $this->line('Mode:          <comment>'.config('vft.mode').'</comment>');
        $this->line('Server:        '.config('vft.base_url'));
        $this->line('Account:       '.(config('vft.email') ?: '<error>VFT_EMAIL not set</error>'));
        $this->line('Default device '.($defaultSn ?: '<error>VFT_DEFAULT_DEVICE_SN not set</error>'));
        $this->newLine();

        try {
            $client->forgetToken();
            $client->signIn();
            $this->info('✓ Signed in.');

            $devices = collect($client->listDevices());
            $this->table(
                ['Serial', 'Name', 'Area', 'Last check-in', 'Default?'],
                $devices->map(fn ($d) => [
                    $d['DevSN'] ?? '?',
                    $d['DevName'] ?? '',
                    $d['Area']['AreaName'] ?? $d['AreaID'] ?? '',
                    $d['LastRequestTime'] ?? 'never',
                    ($d['DevSN'] ?? null) === $defaultSn ? 'yes' : '',
                ])->all(),
            );

            $device = $devices->firstWhere('DevSN', $defaultSn);
            if (! $device) {
                $this->warn('The default device is not registered on this VFT account.');
            } elseif (empty($device['LastRequestTime'])) {
                $this->warn('The device has never checked in with VFT — commands will wait until it connects.');
            }

            $this->line('Areas:     '.count((array) $client->listAreas()));
            $this->line('Employees: '.count((array) $client->listEmployees()));

            return self::SUCCESS;
        } catch (VftApiException $e) {
            $this->error($e->getMessage());
            if ($e->responseBody) {
                $this->line($e->responseBody);
            }

            return self::FAILURE;
        }
    }
}
