<?php

namespace App\Services\Vft;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Builds ZKTeco access-control commands and sends them through VFT's
 * POST /api/devicecmd. Nothing else in the app should build command strings.
 *
 * Commands are TAB-separated `Key=Value` pairs with dates as YYYYMMDD.
 * Every value is cleaned so a name containing a tab/newline can't inject
 * extra fields, and PINs must be 1–9 digits (the Member ID number).
 *
 * Respects VFT_MODE: `off` sends nothing, `log` writes the command to the
 * vft log without sending, `live` sends it.
 */
class VftDeviceCommandService
{
    private const NAME_MAX_LENGTH = 24;

    public function __construct(private readonly VftApiClient $client) {}

    // ---------------------------------------------------------------------
    // Users on the device
    // ---------------------------------------------------------------------

    /**
     * Create the user, or update them if the PIN already exists.
     * Null dates mean "no limit" (sent as 0).
     */
    public function addOrUpdateMember(
        string $pin,
        string $name,
        ?CarbonInterface $startDate,
        ?CarbonInterface $endDate,
        ?string $devSn = null,
    ): VftCommandResult {
        return $this->send('DATA UPDATE user '.$this->fields([
            'CardNo' => '',
            'Pin' => $this->pin($pin),
            'Password' => '',
            'Group' => (string) config('vft.user_group'),
            'StartTime' => $this->date($startDate),
            'EndTime' => $this->date($endDate),
            'Name' => $this->name($name),
            'Privilege' => '0',
            'Disable' => '0',
        ]), $devSn);
    }

    /** Change only the dates a user may enter between. */
    public function setMemberValidity(
        string $pin,
        ?CarbonInterface $startDate,
        ?CarbonInterface $endDate,
        ?string $devSn = null,
    ): VftCommandResult {
        return $this->send('DATA UPDATE user '.$this->fields([
            'Pin' => $this->pin($pin),
            'StartTime' => $this->date($startDate),
            'EndTime' => $this->date($endDate),
        ]), $devSn);
    }

    /**
     * Remove a user completely: door access, fingerprints and the user.
     * Used when a member's PIN changes (fingerprints are tied to the PIN).
     *
     * @return VftCommandResult[]
     */
    public function deleteMember(string $pin, ?string $devSn = null): array
    {
        $pin = $this->pin($pin);

        return [
            $this->revokeDoorAccess($pin, $devSn),
            $this->send("DATA DELETE templatev10 Pin={$pin}", $devSn),
            $this->send("DATA DELETE user Pin={$pin}", $devSn),
        ];
    }

    // ---------------------------------------------------------------------
    // Door access
    // ---------------------------------------------------------------------

    public function grantDoorAccess(string $pin, ?int $doorId = null, ?int $timezoneId = null, ?string $devSn = null): VftCommandResult
    {
        return $this->send('DATA UPDATE userauthorize '.$this->fields([
            'Pin' => $this->pin($pin),
            'AuthorizeTimezoneId' => (string) $this->positiveInt($timezoneId ?? config('vft.access_timezone_id'), 'timezone id'),
            'AuthorizeDoorId' => (string) $this->positiveInt($doorId ?? config('vft.door_id'), 'door id'),
        ]), $devSn);
    }

    public function revokeDoorAccess(string $pin, ?string $devSn = null): VftCommandResult
    {
        return $this->send('DATA DELETE userauthorize Pin='.$this->pin($pin), $devSn);
    }

    /** Unlock a door for a few seconds, e.g. door 1 for 5s → CONTROL DEVICE 01010105. */
    public function openDoor(int $doorId = 1, int $seconds = 5, ?string $devSn = null): VftCommandResult
    {
        if ($doorId < 1 || $doorId > 99) {
            throw new InvalidArgumentException('Door id must be between 1 and 99.');
        }
        if ($seconds < 1 || $seconds > 99) {
            throw new InvalidArgumentException('Open time must be between 1 and 99 seconds.');
        }

        return $this->send(sprintf('CONTROL DEVICE 01%02d01%02d', $doorId, $seconds), $devSn);
    }

    // ---------------------------------------------------------------------
    // Queries (response format still unknown — see the vft log)
    // ---------------------------------------------------------------------

    public function getUsers(?string $devSn = null): VftCommandResult
    {
        return $this->send('DATA QUERY tablename=user,fielddesc=*,filter=*', $devSn);
    }

    public function getFingerprintTemplates(?string $devSn = null): VftCommandResult
    {
        return $this->send('DATA QUERY tablename=templatev10,fielddesc=*,filter=*', $devSn);
    }

    public function getTransactions(?string $devSn = null): VftCommandResult
    {
        return $this->send('DATA QUERY tablename=transaction,fielddesc=*,filter=*', $devSn);
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    public function mode(): string
    {
        $mode = config('vft.mode');

        return in_array($mode, ['off', 'log', 'live'], true) ? $mode : 'off';
    }

    private function send(string $content, ?string $devSn): VftCommandResult
    {
        $devSn = $this->deviceSn($devSn);
        $mode = $this->mode();

        if ($mode === 'off') {
            return new VftCommandResult($mode, $devSn, $content, sent: false);
        }

        if ($mode === 'log') {
            Log::channel('vft')->info('[dry run] device command not sent', [
                'DevSN' => $devSn,
                'Content' => $content,
            ]);

            return new VftCommandResult($mode, $devSn, $content, sent: false);
        }

        $response = $this->client->sendDeviceCommand($devSn, $content);

        return new VftCommandResult($mode, $devSn, $content, sent: true, response: $response);
    }

    private function deviceSn(?string $devSn): string
    {
        $devSn = $this->clean($devSn ?? (string) config('vft.default_device_sn'));

        if ($devSn === '') {
            throw new InvalidArgumentException('No device serial number: set VFT_DEFAULT_DEVICE_SN.');
        }

        return $devSn;
    }

    /** @param array<string, string> $fields */
    private function fields(array $fields): string
    {
        return collect($fields)
            ->map(fn (string $value, string $key) => $key.'='.$this->clean($value))
            ->implode("\t");
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

    private function positiveInt(int $value, string $label): int
    {
        if ($value < 1) {
            throw new InvalidArgumentException("Invalid {$label}: {$value}.");
        }

        return $value;
    }
}
