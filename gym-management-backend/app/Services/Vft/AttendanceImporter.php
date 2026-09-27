<?php

namespace App\Services\Vft;

use App\Models\Attendance;
use App\Models\DevicePunch;
use App\Models\Member;
use App\Models\StaffAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Saves door punches and folds them into daily attendance.
 *
 * - Every punch is stored once in `device_punches` (unique on device + PIN +
 *   time), so re-syncing the same transactions never double-counts.
 * - Only "came in" events count (config vft.attendance_event_types; rows
 *   with no event type are counted).
 * - PIN = Member ID number. Members → `attendance`; staff registrations →
 *   `staff_attendance`. Unknown PINs are kept in device_punches only.
 * - One row per person per day: earliest punch = check-in, latest =
 *   check-out (kept if a manual entry was earlier/later). Status: Present.
 */
class AttendanceImporter
{
    private const TIME_FORMAT = 'h:i A';

    /**
     * @param  list<array{pin: string, punched_at: CarbonImmutable, event_type: ?string, raw: array}>  $punches
     * @return array{new: int, duplicate: int, members: int, staff: int, unknown: int, ignored: int}
     */
    public function import(string $devSn, array $punches): array
    {
        $stats = ['new' => 0, 'duplicate' => 0, 'members' => 0, 'staff' => 0, 'unknown' => 0, 'ignored' => 0];
        $countedTypes = config('vft.attendance_event_types');

        foreach ($punches as $punch) {
            DB::transaction(function () use ($devSn, $punch, $countedTypes, &$stats) {
                $member = Member::with('staffAccount')->where('member_id_number', $punch['pin'])->first();
                $staff = $member?->staffAccount;

                $record = DevicePunch::firstOrCreate(
                    [
                        'device_sn' => $devSn,
                        'pin' => $punch['pin'],
                        'punched_at' => $punch['punched_at']->format('Y-m-d H:i:s'),
                    ],
                    [
                        'event_type' => $punch['event_type'],
                        'member_id' => $member?->id,
                        'user_id' => $staff?->id,
                        'raw' => $punch['raw'],
                    ],
                );

                if (! $record->wasRecentlyCreated) {
                    $stats['duplicate']++;

                    return;
                }

                $stats['new']++;

                if ($punch['event_type'] !== null && ! in_array($punch['event_type'], $countedTypes, true)) {
                    $stats['ignored']++;

                    return;
                }

                if ($staff) {
                    $this->recordStaff($staff, $punch['punched_at']);
                    $stats['staff']++;
                } elseif ($member) {
                    $this->recordMember($member, $punch['punched_at']);
                    $stats['members']++;
                } else {
                    $stats['unknown']++;
                }
            });
        }

        return $stats;
    }

    private function recordMember(Member $member, CarbonImmutable $at): void
    {
        $row = Attendance::where('member_id', $member->id)
            ->whereDate('date', $at->toDateString())
            ->first();

        if (! $row) {
            Attendance::create([
                'member_id' => $member->id,
                'member_name' => $member->full_name,
                'email' => $member->email,
                'date' => $at->toDateString(),
                'check_in_time' => $at->format(self::TIME_FORMAT),
                'check_out_time' => null,
                'status' => 'Present',
            ]);

            return;
        }

        $row->update($this->mergeTimes($row->check_in_time, $row->check_out_time, $at) + ['status' => 'Present']);
    }

    private function recordStaff($staff, CarbonImmutable $at): void
    {
        $row = StaffAttendance::firstOrNew(['user_id' => $staff->id, 'date' => $at->toDateString()]);

        if (! $row->exists) {
            $row->fill([
                'staff_name' => $staff->full_name ?? 'Staff',
                'check_in_time' => $at->format(self::TIME_FORMAT),
                'status' => 'Present',
            ])->save();

            return;
        }

        $row->update($this->mergeTimes($row->check_in_time, $row->check_out_time, $at));
    }

    /** New earliest time becomes check-in, new latest becomes check-out. */
    private function mergeTimes(?string $in, ?string $out, CarbonImmutable $at): array
    {
        $times = array_filter([$this->parseTime($in, $at), $this->parseTime($out, $at), $at]);
        usort($times, fn ($a, $b) => $a <=> $b);

        $first = reset($times);
        $last = end($times);

        return [
            'check_in_time' => $first->format(self::TIME_FORMAT),
            'check_out_time' => $last->equalTo($first) ? null : $last->format(self::TIME_FORMAT),
        ];
    }

    private function parseTime(?string $time, CarbonImmutable $day): ?CarbonImmutable
    {
        if (! $time) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat(self::TIME_FORMAT, $time, $day->getTimezone());
        } catch (\Throwable) {
            return null; // a manually typed time in another format is left out
        }

        return $parsed ? $day->setTime($parsed->hour, $parsed->minute) : null;
    }
}
