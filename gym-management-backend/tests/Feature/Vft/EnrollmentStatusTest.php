<?php

namespace Tests\Feature\Vft;

use App\Models\DeviceEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Vft\Concerns\CreatesGymRecords;
use Tests\Feature\Vft\Concerns\FakesVft;
use Tests\TestCase;

class EnrollmentStatusTest extends TestCase
{
    use CreatesGymRecords, FakesVft, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['logging.channels.vft' => ['driver' => 'single', 'path' => storage_path('logs/vft-test.log')]]);
        $this->fakeVft();
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_admin' => true]);
        $this->actingAs(User::create(['full_name' => 'Admin', 'email' => 'admin@example.com', 'role_id' => $role->id, 'status' => 'Active']), 'sanctum');
    }

    private function command(int $id, string $command, ?int $return, string $at = '09/28/2026, 10:00:00'): array
    {
        return [
            'ID' => $id,
            'DevSN' => 'TESTSN001',
            'Command' => $command,
            'CommitTime' => $at,
            'ResponseTime' => $return === null ? '01/01/1999, 14:14:14' : $at,
            'ReturnValue' => $return === null ? '' : "ID={$id}&Return={$return}&CMD=ENROLL_BIO",
            'IsProcessed' => $return === null ? 0 : 1,
        ];
    }

    private function memberWithPin(string $pin = '12')
    {
        config(['vft.mode' => 'off']);
        $member = $this->member(['member_id_number' => $pin]);
        config(['vft.mode' => 'live']);

        return $member;
    }

    public function test_reports_registered_failed_and_waiting_per_face_and_finger(): void
    {
        $member = $this->memberWithPin();
        $this->vftDeviceCommands = [
            $this->command(10, 'Enroll User 12, Face', 6, '09/28/2026, 09:00:00'),
            $this->command(11, 'Enroll User 12, Face', 0, '09/28/2026, 09:05:00'),
            $this->command(12, 'Enroll User 12, Finger 6', 6),
            $this->command(13, 'Enroll User 12, Finger 2', null),
            $this->command(14, 'Enroll User 99, Finger 1', 0),
            $this->command(15, 'Door Open', 0),
        ];

        $this->getJson("/api/members/{$member->id}/enrollments")
            ->assertOk()
            ->assertJson(['refreshError' => null])
            ->assertJsonPath('enrollments', [
                ['kind' => 'face', 'fingerId' => null, 'status' => 'registered', 'returnCode' => 0, 'lastAttemptAt' => '2026-09-28T03:35:00+00:00'],
                ['kind' => 'finger', 'fingerId' => 2, 'status' => 'pending', 'returnCode' => null, 'lastAttemptAt' => '2026-09-28T04:30:00+00:00'],
                ['kind' => 'finger', 'fingerId' => 6, 'status' => 'failed', 'returnCode' => 6, 'lastAttemptAt' => '2026-09-28T04:30:00+00:00'],
            ]);
    }

    public function test_a_later_failed_retry_does_not_undo_a_registration(): void
    {
        $member = $this->memberWithPin();
        $this->vftDeviceCommands = [
            $this->command(20, 'Enroll User 12, Finger 1', 0, '09/28/2026, 09:00:00'),
            $this->command(21, 'Enroll User 12, Finger 1', 5, '09/28/2026, 11:00:00'),
        ];

        $this->getJson("/api/members/{$member->id}/enrollments")
            ->assertJsonPath('enrollments.0.status', 'registered');
    }

    public function test_history_is_kept_after_it_drops_off_the_device_list(): void
    {
        $member = $this->memberWithPin();
        $this->vftDeviceCommands = [$this->command(30, 'Enroll User 12, Face', 0)];
        $this->getJson("/api/members/{$member->id}/enrollments");

        $this->vftDeviceCommands = [];
        $this->getJson("/api/members/{$member->id}/enrollments")
            ->assertJsonPath('enrollments.0.status', 'registered');
    }

    public function test_waiting_scan_is_updated_once_the_device_answers(): void
    {
        $member = $this->memberWithPin();
        $this->vftDeviceCommands = [$this->command(40, 'Enroll User 12, Finger 3', null)];
        $this->getJson("/api/members/{$member->id}/enrollments")->assertJsonPath('enrollments.0.status', 'pending');

        $this->vftDeviceCommands = [$this->command(40, 'Enroll User 12, Finger 3', 0)];
        $this->getJson("/api/members/{$member->id}/enrollments")->assertJsonPath('enrollments.0.status', 'registered');

        $this->assertSame(1, DeviceEnrollment::count());
    }

    public function test_vft_unreachable_falls_back_to_stored_status(): void
    {
        $member = $this->memberWithPin();
        $this->vftDeviceCommands = [$this->command(50, 'Enroll User 12, Face', 0)];
        $this->getJson("/api/members/{$member->id}/enrollments");

        $this->vftFailure = fn (string $call) => str_starts_with($call, 'GET /api/devicecmd/') ? Http::response('down', 500) : null;

        $this->getJson("/api/members/{$member->id}/enrollments")
            ->assertOk()
            ->assertJsonPath('enrollments.0.status', 'registered')
            ->assertJsonPath('refreshError', 'Could not reach the door device service, showing the last known status.');
    }

    public function test_member_without_a_door_pin_has_nothing_and_vft_is_not_called(): void
    {
        $member = $this->memberWithPin('CARD-1');

        $this->getJson("/api/members/{$member->id}/enrollments")
            ->assertOk()->assertJsonPath('enrollments', []);

        Http::assertNothingSent();
    }

    public function test_staff_status_uses_their_registration_pin(): void
    {
        config(['vft.mode' => 'off']);
        $staff = $this->staff($this->member(['member_id_number' => '7']));
        config(['vft.mode' => 'live']);
        $this->vftDeviceCommands = [$this->command(60, 'Enroll User 7, Finger 0', 0)];

        $this->getJson("/api/setup/users/{$staff->id}/enrollments")
            ->assertOk()
            ->assertJsonPath('enrollments.0.fingerId', 0)
            ->assertJsonPath('enrollments.0.status', 'registered');
    }
}
