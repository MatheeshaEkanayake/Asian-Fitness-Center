<?php

namespace App\Services\Vft;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns a VFT webhook body into punches. VFT POSTs a JSON array (a single
 * object is accepted too):
 *
 *   [{ "EmpId": "168", "AttTime": "2024-01-15 08:30:15", "CheckingStatus": "0",
 *      "VerifyType": "1", "Event": "3", "DeviceID": "QEM1253800017" }]
 *
 * EmpId is the Member ID number; AttTime is device-local time
 * (config vft.timezone). Rows without an EmpId or a readable time are
 * skipped.
 */
class WebhookPunchParser
{
    /**
     * @return list<array{device_sn: string, pin: string, punched_at: CarbonImmutable, event_type: ?string, raw: array}>
     */
    public function parse(mixed $body): array
    {
        if (! is_array($body)) {
            return [];
        }

        $rows = array_is_list($body) ? $body : [$body];
        $punches = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $pin = trim((string) ($row['EmpId'] ?? ''));
            $time = $this->time($row['AttTime'] ?? null);

            if ($pin === '' || ! $time) {
                continue;
            }

            $punches[] = [
                'device_sn' => trim((string) ($row['DeviceID'] ?? '')) ?: (string) config('vft.default_device_sn'),
                'pin' => $pin,
                'punched_at' => $time,
                'event_type' => isset($row['Event']) ? (string) $row['Event'] : null,
                'raw' => $row,
            ];
        }

        return $punches;
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, config('vft.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
