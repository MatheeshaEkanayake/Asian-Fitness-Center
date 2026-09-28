<?php

namespace Tests\Feature\Vft;

use App\Models\Attendance;
use App\Models\DevicePunch;
use App\Models\StaffAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Vft\Concerns\CreatesGymRecords;
use Tests\TestCase;

class VftWebhookTest extends TestCase
{
    use CreatesGymRecords, RefreshDatabase;

    private function punch(string $pin, string $time, string $event = '3'): array
    {
        return ['EmpId' => $pin, 'AttTime' => $time, 'CheckingStatus' => '0', 'VerifyType' => '1', 'Event' => $event, 'DeviceID' => 'TESTSN001'];
    }

    public function test_punches_become_member_and_staff_attendance(): void
    {
        $member = $this->member(['full_name' => 'Kamal', 'member_id_number' => '42']);
        $registration = $this->member(['full_name' => 'Staff One', 'member_id_number' => '7']);
        $this->staff($registration);

        $this->postJson('/api/vft/webhook/test-webhook-secret', [
            $this->punch('42', '2026-10-15 06:30:00'),
            $this->punch('42', '2026-10-15 08:05:00'),
            $this->punch('7', '2026-10-15 07:00:00'),
            $this->punch('999', '2026-10-15 07:10:00'),
        ])->assertOk()->assertJson(['received' => 4, 'new' => 4, 'members' => 2, 'staff' => 1, 'unknown' => 1]);

        $row = Attendance::where('member_id', $member->id)->sole();
        $this->assertSame('06:30 AM', $row->check_in_time);
        $this->assertSame('08:05 AM', $row->check_out_time);
        $this->assertSame(1, StaffAttendance::count());
        $this->assertSame('3', DevicePunch::first()->event_type);
    }

    public function test_repeated_delivery_is_not_double_counted(): void
    {
        $this->member(['member_id_number' => '42']);
        $body = [$this->punch('42', '2026-10-15 06:30:00')];

        $this->postJson('/api/vft/webhook/test-webhook-secret', $body)->assertOk();
        $this->postJson('/api/vft/webhook/test-webhook-secret', $body)->assertOk()->assertJson(['new' => 0, 'duplicate' => 1]);

        $this->assertSame(1, DevicePunch::count());
        $this->assertSame(1, Attendance::count());
    }

    public function test_excluded_event_codes_are_kept_but_not_counted(): void
    {
        config(['vft.attendance_excluded_events' => ['27']]);
        $this->member(['member_id_number' => '42']);

        $this->postJson('/api/vft/webhook/test-webhook-secret', [$this->punch('42', '2026-10-15 06:30:00', '27')])
            ->assertOk()->assertJson(['ignored' => 1, 'members' => 0]);

        $this->assertSame(0, Attendance::count());
        $this->assertSame(1, DevicePunch::count());
    }

    public function test_wrong_or_missing_secret_is_404_and_records_nothing(): void
    {
        $this->member(['member_id_number' => '42']);
        $body = [$this->punch('42', '2026-10-15 06:30:00')];

        $this->postJson('/api/vft/webhook/wrong-secret', $body)->assertNotFound();

        config(['vft.webhook_secret' => null]);
        $this->postJson('/api/vft/webhook/test-webhook-secret', $body)->assertNotFound();

        $this->assertSame(0, DevicePunch::count());
    }
}
