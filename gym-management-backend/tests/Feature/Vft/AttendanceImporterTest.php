<?php

namespace Tests\Feature\Vft;

use App\Models\Attendance;
use App\Models\DevicePunch;
use App\Models\StaffAttendance;
use App\Services\Vft\AttendanceImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Vft\Concerns\CreatesGymRecords;
use Tests\TestCase;

class AttendanceImporterTest extends TestCase
{
    use CreatesGymRecords, RefreshDatabase;

    private function punch(string $pin, string $time, ?string $eventType = '0'): array
    {
        return [
            'pin' => $pin,
            'punched_at' => CarbonImmutable::parse($time, 'Asia/Colombo'),
            'event_type' => $eventType,
            'raw' => ['pin' => $pin, 'time' => $time],
        ];
    }

    public function test_member_punches_become_one_attendance_row_per_day(): void
    {
        $member = $this->member(['full_name' => 'Kamal', 'member_id_number' => '42']);

        $stats = app(AttendanceImporter::class)->import('SN1', [
            $this->punch('42', '2026-10-15 18:40:00'),
            $this->punch('42', '2026-10-15 06:30:00'),
            $this->punch('42', '2026-10-15 07:45:00'),
        ]);

        $this->assertSame(3, $stats['new']);
        $row = Attendance::where('member_id', $member->id)->sole();
        $this->assertSame('2026-10-15', $row->date->toDateString());
        $this->assertSame('06:30 AM', $row->check_in_time);
        $this->assertSame('06:40 PM', $row->check_out_time);
        $this->assertSame('Present', $row->status);
        $this->assertSame('Kamal', $row->member_name);
    }

    public function test_re_importing_the_same_punches_skips_duplicates(): void
    {
        $this->member(['member_id_number' => '42']);
        $punches = [$this->punch('42', '2026-10-15 06:30:00'), $this->punch('42', '2026-10-15 08:00:00')];

        app(AttendanceImporter::class)->import('SN1', $punches);
        $stats = app(AttendanceImporter::class)->import('SN1', $punches);

        $this->assertSame(0, $stats['new']);
        $this->assertSame(2, $stats['duplicate']);
        $this->assertSame(2, DevicePunch::count());
        $this->assertSame(1, Attendance::count());
    }

    public function test_single_punch_has_no_check_out(): void
    {
        $this->member(['member_id_number' => '42']);

        app(AttendanceImporter::class)->import('SN1', [$this->punch('42', '2026-10-15 06:30:00')]);

        $this->assertNull(Attendance::sole()->check_out_time);
    }

    public function test_keeps_earlier_manual_check_in(): void
    {
        $member = $this->member(['member_id_number' => '42']);
        Attendance::create(['member_id' => $member->id, 'member_name' => 'X', 'date' => '2026-10-15',
            'check_in_time' => '05:50 AM', 'status' => 'Present']);

        app(AttendanceImporter::class)->import('SN1', [$this->punch('42', '2026-10-15 07:00:00')]);

        $row = Attendance::sole();
        $this->assertSame('05:50 AM', $row->check_in_time);
        $this->assertSame('07:00 AM', $row->check_out_time);
    }

    public function test_staff_punches_go_to_staff_attendance_only(): void
    {
        $registration = $this->member(['full_name' => 'Staff One', 'member_id_number' => '7']);
        $user = $this->staff($registration);

        $stats = app(AttendanceImporter::class)->import('SN1', [
            $this->punch('7', '2026-10-15 08:00:00'),
            $this->punch('7', '2026-10-15 17:00:00'),
        ]);

        $this->assertSame(2, $stats['staff']);
        $this->assertSame(0, Attendance::count());
        $row = StaffAttendance::sole();
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame('Staff One', $row->staff_name);
        $this->assertSame('08:00 AM', $row->check_in_time);
        $this->assertSame('05:00 PM', $row->check_out_time);
        $this->assertSame($user->id, DevicePunch::first()->user_id);
    }

    public function test_denied_events_and_unknown_pins_are_stored_but_not_counted(): void
    {
        $this->member(['member_id_number' => '42']);

        $stats = app(AttendanceImporter::class)->import('SN1', [
            $this->punch('42', '2026-10-15 06:30:00', '27'),
            $this->punch('999', '2026-10-15 06:31:00'),
        ]);

        $this->assertSame(['new' => 2, 'duplicate' => 0, 'members' => 0, 'staff' => 0, 'unknown' => 1, 'ignored' => 1], $stats);
        $this->assertSame(0, Attendance::count());
        $this->assertSame(2, DevicePunch::count());
    }

    public function test_member_without_email_can_get_attendance(): void
    {
        $this->member(['member_id_number' => '42', 'email' => null]);

        app(AttendanceImporter::class)->import('SN1', [$this->punch('42', '2026-10-15 06:30:00')]);

        $this->assertNull(Attendance::sole()->email);
    }
}
