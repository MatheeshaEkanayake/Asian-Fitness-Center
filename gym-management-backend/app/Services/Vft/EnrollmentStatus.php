<?php

namespace App\Services\Vft;

use App\Models\DeviceEnrollment;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Whether a person has a face / fingerprints registered on the door device.
 *
 * VFT has no "what is enrolled" field, so this is worked out from the
 * device's command list: each "Enroll User 12, Finger 2" command gets a
 * ReturnValue from the device once processed, where Return=0 is success.
 * Other codes (5, 6, -12 seen so far) are undocumented failures such as a
 * timeout or cancelled scan.
 *
 * Blind spots: enrollments done from the device's own menu never show up,
 * and deleting a fingerprint on the device isn't reported.
 */
class EnrollmentStatus
{
    public function __construct(private readonly VftApiClient $client) {}

    /**
     * Copy the device's enroll commands into device_enrollments.
     *
     * @return ?string an error message if VFT couldn't be read (stored rows are still usable)
     */
    public function refresh(?string $devSn = null): ?string
    {
        $devSn ??= (string) config('vft.default_device_sn');

        if (config('vft.mode') === 'off' || $devSn === '') {
            return null;
        }

        try {
            $commands = (array) $this->client->listDeviceCommands($devSn);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        foreach ($commands as $command) {
            $row = $this->parse($command, $devSn);
            if ($row) {
                DeviceEnrollment::updateOrCreate(['vft_command_id' => $row['vft_command_id']], $row);
            }
        }

        return null;
    }

    /**
     * One entry per face / finger that has ever been attempted:
     * registered if any attempt succeeded, otherwise the latest attempt's status.
     *
     * @return list<array{kind: string, fingerId: ?int, status: string, returnCode: ?int, lastAttemptAt: ?string}>
     */
    public function forPin(string $pin): array
    {
        return DeviceEnrollment::where('pin', $pin)
            ->orderByDesc('requested_at')
            ->orderByDesc('vft_command_id')
            ->get()
            ->groupBy(fn (DeviceEnrollment $e) => $e->kind.':'.$e->finger_id)
            ->map(function ($attempts) {
                $latest = $attempts->first();
                $success = $attempts->firstWhere('status', 'registered');

                return [
                    'kind' => $latest->kind,
                    'fingerId' => $latest->finger_id,
                    'status' => $success ? 'registered' : $latest->status,
                    'returnCode' => $success ? 0 : $latest->return_code,
                    'lastAttemptAt' => $latest->requested_at?->toIso8601String(),
                ];
            })
            ->sortBy(fn ($slot) => [$slot['kind'] === 'face' ? 0 : 1, $slot['fingerId'] ?? -1])
            ->values()
            ->all();
    }

    /** @return ?array<string, mixed> null if the command isn't an enrollment */
    public function parse(array $command, string $devSn): ?array
    {
        if (! preg_match('/^Enroll User (\d+), (?:(Face)|Finger (\d+))$/i', trim((string) ($command['Command'] ?? '')), $m)) {
            return null;
        }

        $processed = (int) ($command['IsProcessed'] ?? 0) === 1;
        $code = preg_match('/(?:^|&)Return=(-?\d+)/', (string) ($command['ReturnValue'] ?? ''), $r) ? (int) $r[1] : null;

        return [
            'vft_command_id' => (int) $command['ID'],
            'device_sn' => (string) ($command['DevSN'] ?? $devSn),
            'pin' => $m[1],
            'kind' => ! empty($m[2]) ? 'face' : 'finger',
            'finger_id' => ! empty($m[2]) ? null : (int) $m[3],
            'status' => ! $processed ? 'pending' : ($code === 0 ? 'registered' : 'failed'),
            'return_code' => $code,
            'requested_at' => $this->time($command['CommitTime'] ?? null),
            'completed_at' => $processed ? $this->time($command['ResponseTime'] ?? null) : null,
        ];
    }

    /** VFT times look like "09/28/2026, 15:33:17"; unprocessed ones are "01/01/1999, …". */
    private function time(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            $time = CarbonImmutable::createFromFormat('m/d/Y, H:i:s', $value, config('vft.timezone'));
        } catch (Throwable) {
            return null;
        }

        // Stored in the app's timezone like every other timestamp.
        return $time && $time->year > 2000 ? $time->setTimezone(config('app.timezone')) : null;
    }
}
