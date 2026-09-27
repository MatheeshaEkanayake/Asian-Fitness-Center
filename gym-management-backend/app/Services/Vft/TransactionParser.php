<?php

namespace App\Services\Vft;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns whatever VFT returns for `DATA QUERY tablename=transaction` into
 * punches. The real format is undocumented (it may not even return data —
 * the command could just be queued), so this accepts the likely shapes:
 *
 *   - JSON rows: [{ "pin": "42", "time": "2026-09-26 06:30:00", ... }]
 *     possibly wrapped in { data | rows | transactions | result: ... }
 *   - ZKTeco text, one per line:
 *     "transaction cardno=0\tpin=42\teventtype=0\tdoorid=1\ttime_second=…"
 *
 * Keys are matched case-insensitively. ZKTeco's packed `time_second` is
 * decoded; plain date strings are parsed in the device time zone.
 * Rows without a PIN or a readable time are skipped.
 */
class TransactionParser
{
    /**
     * @return list<array{pin: string, punched_at: CarbonImmutable, event_type: ?string, raw: array}>
     */
    public function parse(mixed $response): array
    {
        $punches = [];

        foreach ($this->rows($response) as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $pin = trim((string) ($row['pin'] ?? $row['empid'] ?? $row['userid'] ?? ''));
            $time = $this->time($row);

            if ($pin === '' || ! $time) {
                continue;
            }

            $eventType = $row['eventtype'] ?? $row['event_type'] ?? null;

            $punches[] = [
                'pin' => $pin,
                'punched_at' => $time,
                'event_type' => $eventType === null ? null : (string) $eventType,
                'raw' => $row,
            ];
        }

        return $punches;
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $response): array
    {
        if (is_string($response)) {
            $decoded = json_decode($response, true);

            return is_array($decoded) ? $this->rows($decoded) : $this->textRows($response);
        }

        if (! is_array($response)) {
            return [];
        }

        foreach (['data', 'rows', 'transactions', 'transaction', 'result', 'records'] as $key) {
            if (array_key_exists($key, $response)) {
                return $this->rows($response[$key]);
            }
        }

        if (array_is_list($response)) {
            return array_values(array_filter(
                array_map(fn ($row) => is_string($row) ? ($this->textRows($row)[0] ?? null) : $row, $response),
                'is_array',
            ));
        }

        // A single row object.
        return [$response];
    }

    /** @return list<array<string, string>> */
    private function textRows(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = preg_replace('/^\s*transaction\s+/i', '', $line);
            $row = [];

            foreach (preg_split('/\t+/', trim($line)) as $pair) {
                if (str_contains($pair, '=')) {
                    [$key, $value] = explode('=', $pair, 2);
                    $row[trim($key)] = trim($value);
                }
            }

            if ($row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function time(array $row): ?CarbonImmutable
    {
        $tz = config('vft.timezone');

        if (isset($row['time_second']) && is_numeric($row['time_second']) && (int) $row['time_second'] > 0) {
            return $this->decodeTimeSecond((int) $row['time_second'], $tz);
        }

        foreach (['time', 'datetime', 'checktime', 'punch_time', 'logtime', 'date'] as $key) {
            if (! empty($row[$key]) && is_string($row[$key])) {
                try {
                    return CarbonImmutable::parse($row[$key], $tz);
                } catch (Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * ZKTeco packs date+time into one number:
     * ((((year-2000)*12 + month-1)*31 + day-1)*24 + hour)*60 + minute)*60 + second
     */
    public function decodeTimeSecond(int $value, string $tz): CarbonImmutable
    {
        $second = $value % 60;
        $value = intdiv($value, 60);
        $minute = $value % 60;
        $value = intdiv($value, 60);
        $hour = $value % 24;
        $value = intdiv($value, 24);
        $day = $value % 31 + 1;
        $value = intdiv($value, 31);
        $month = $value % 12 + 1;
        $year = intdiv($value, 12) + 2000;

        return CarbonImmutable::create($year, $month, $day, $hour, $minute, $second, $tz);
    }
}
