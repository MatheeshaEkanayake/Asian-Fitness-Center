<?php

namespace App\Services\Vft;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The door-device actions the app uses, on top of VftApiClient. Nothing else
 * in the app should call the device endpoints directly.
 *
 * People are VFT "employees" whose EmpId is the Member ID number (1–9
 * digits). Putting someone on the door takes four steps, in this order,
 * because the device works through its queued commands in order:
 *
 *   upsertPerson()     cloud: add, or rename if they already exist
 *   transferToDevice() cloud → device (user + any fingerprints/face)
 *   setValidity()      device: the dates they may enter between
 *   grantAccess()      device: door access on
 *
 * Respects VFT_MODE: `off` sends nothing, `log` writes each action to the
 * vft log without sending, `live` sends it.
 */
class VftDeviceCommandService
{
    private const NAME_MAX_LENGTH = 24;

    public const AREA_CACHE_KEY = 'vft.default_device_area';

    public function __construct(private readonly VftApiClient $client) {}

    // ---------------------------------------------------------------------
    // People
    // ---------------------------------------------------------------------

    /**
     * Add the person to the VFT cloud, or update their name if their EmpId
     * is already there (adding an existing EmpId fails with "Validation
     * error", and editing a missing one still reports success).
     */
    public function upsertPerson(string $pin, string $name, ?string $devSn = null): VftCommandResult
    {
        $pin = $this->pin($pin);
        $name = $this->name($name);
        $devSn = $this->deviceSn($devSn);

        return $this->run($devSn, 'upsert-employee', ['EmpId' => $pin, 'EmpName' => $name], function () use ($pin, $name, $devSn) {
            $existing = collect((array) $this->client->listEmployees())
                ->first(fn ($employee) => (string) ($employee['EmpId'] ?? '') === $pin);

            if (! $existing) {
                return $this->client->createEmployee(['EmpId' => $pin, 'EmpName' => $name, 'AreaID' => $this->areaId($devSn)]);
            }

            return ($existing['EmpName'] ?? null) === $name
                ? ['message' => 'Already up to date']
                : $this->client->updateEmployee($pin, ['EmpName' => $name]);
        });
    }

    public function transferToDevice(string $pin, ?string $devSn = null): VftCommandResult
    {
        $pin = $this->pin($pin);
        $devSn = $this->deviceSn($devSn);

        return $this->run($devSn, 'syncsometoone', ['empidlist' => $pin],
            fn () => $this->client->syncEmployeesToDevice($devSn, [$pin]));
    }

    /** Remove the person from every device (user + fingerprints/face) and from the cloud. */
    public function removePerson(string $pin, ?string $devSn = null): VftCommandResult
    {
        $pin = $this->pin($pin);

        return $this->run($this->deviceSn($devSn), 'delete-employee', ['EmpId' => $pin], function () use ($pin) {
            return [
                'fromdev' => $this->client->deleteEmployeeFromDevices($pin),
                'cloud' => $this->client->deleteEmployee($pin),
            ];
        });
    }

    // ---------------------------------------------------------------------
    // Door access
    // ---------------------------------------------------------------------

    /** The dates they may enter between; null means no limit (sent as 0). */
    public function setValidity(string $pin, ?CarbonInterface $startDate, ?CarbonInterface $endDate, ?string $devSn = null): VftCommandResult
    {
        $pin = $this->pin($pin);
        $devSn = $this->deviceSn($devSn);
        $params = ['EmployeeId' => $pin, 'StartDate' => $this->date($startDate), 'EndDate' => $this->date($endDate)];

        return $this->run($devSn, 'validperiod', $params,
            fn () => $this->client->setValidPeriod($devSn, $pin, $params['StartDate'], $params['EndDate']));
    }

    public function grantAccess(string $pin, ?string $devSn = null): VftCommandResult
    {
        $pin = $this->pin($pin);
        $devSn = $this->deviceSn($devSn);

        return $this->run($devSn, 'accessgrant', ['EmployeeId' => $pin], fn () => $this->client->grantAccess($devSn, $pin));
    }

    /** Door access off; the person and their fingerprints stay on the device. */
    public function blockAccess(string $pin, ?string $devSn = null): VftCommandResult
    {
        $pin = $this->pin($pin);
        $devSn = $this->deviceSn($devSn);

        return $this->run($devSn, 'accessblock', ['EmployeeId' => $pin], fn () => $this->client->blockAccess($devSn, $pin));
    }

    public function openDoor(?string $devSn = null): VftCommandResult
    {
        $devSn = $this->deviceSn($devSn);

        return $this->run($devSn, 'dooropen', [], fn () => $this->client->openDoor($devSn));
    }

    // ---------------------------------------------------------------------
    // Enrollment — the device waits for the person to scan
    // ---------------------------------------------------------------------

    /** @param int $fingerId 0–9, in the device's own finger numbering */
    public function enrollFinger(string $pin, int $fingerId, ?string $devSn = null): VftCommandResult
    {
        if ($fingerId < 0 || $fingerId > 9) {
            throw new InvalidArgumentException('Finger must be 0–9.');
        }

        $pin = $this->pin($pin);
        $devSn = $this->deviceSn($devSn);

        return $this->run($devSn, 'enrollfinger', ['employeeId' => $pin, 'fingerId' => $fingerId],
            fn () => $this->client->enrollFinger($devSn, $pin, $fingerId));
    }

    public function enrollFace(string $pin, ?string $devSn = null): VftCommandResult
    {
        $pin = $this->pin($pin);
        $devSn = $this->deviceSn($devSn);

        return $this->run($devSn, 'enrollface', ['employeeId' => $pin], fn () => $this->client->enrollFace($devSn, $pin));
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    public function mode(): string
    {
        $mode = config('vft.mode');

        return in_array($mode, ['off', 'log', 'live'], true) ? $mode : 'off';
    }

    /** @param array<string, mixed> $params */
    private function run(string $devSn, string $action, array $params, callable $send): VftCommandResult
    {
        $mode = $this->mode();

        if ($mode === 'off') {
            return new VftCommandResult($mode, $devSn, $action, $params, sent: false);
        }

        if ($mode === 'log') {
            Log::channel('vft')->info("[dry run] {$action} not sent", ['DevSN' => $devSn] + $params);

            return new VftCommandResult($mode, $devSn, $action, $params, sent: false);
        }

        return new VftCommandResult($mode, $devSn, $action, $params, sent: true, response: $send());
    }

    /** The default device's area, from GET /api/device (cached). New people go in it. */
    private function areaId(string $devSn): int
    {
        return Cache::remember(self::AREA_CACHE_KEY.'.'.$devSn, config('vft.area_cache_seconds'), function () use ($devSn) {
            $device = collect((array) $this->client->listDevices())->firstWhere('DevSN', $devSn);
            $areaId = $device['Area']['AreaID'] ?? $device['AreaID'] ?? null;

            if (! is_numeric($areaId)) {
                throw new VftApiException("Device {$devSn} is not registered on this VFT account, or has no area.");
            }

            return (int) $areaId;
        });
    }

    private function deviceSn(?string $devSn): string
    {
        $devSn = $this->clean($devSn ?? (string) config('vft.default_device_sn'));

        if ($devSn === '') {
            throw new InvalidArgumentException('No device serial number: set VFT_DEFAULT_DEVICE_SN.');
        }

        return $devSn;
    }

    public function pin(string $pin): string
    {
        $pin = trim($pin);

        if (! preg_match('/^\d{1,9}$/', $pin)) {
            throw new InvalidArgumentException("Invalid device PIN \"{$pin}\": must be 1–9 digits.");
        }

        return $pin;
    }

    private function name(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', $this->clean($name));

        return mb_substr(trim($name), 0, self::NAME_MAX_LENGTH);
    }

    /** Strip tabs, newlines and other control characters. */
    private function clean(string $value): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value));
    }

    private function date(?CarbonInterface $date): string
    {
        return $date ? $date->format('Ymd') : '0';
    }
}
