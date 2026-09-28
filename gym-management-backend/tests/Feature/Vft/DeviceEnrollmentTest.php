<?php

namespace Tests\Feature\Vft;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Vft\Concerns\CreatesGymRecords;
use Tests\Feature\Vft\Concerns\FakesVft;
use Tests\TestCase;

class DeviceEnrollmentTest extends TestCase
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

    private function onDevice(array $attributes = [])
    {
        // Created while VFT is off so observers don't queue a sync.
        config(['vft.mode' => 'off']);
        $member = $this->member($attributes + ['member_id_number' => '42', 'device_pin_synced' => '42']);
        config(['vft.mode' => 'live']);

        return $member;
    }

    public function test_enroll_finger_for_a_member(): void
    {
        $member = $this->onDevice();

        $this->postJson("/api/members/{$member->id}/enroll", ['type' => 'finger', 'finger_id' => 6])
            ->assertOk()->assertJson(['sent' => true]);

        $this->assertSame(['POST /api/devicecmd/enrollfinger?DevSN=TESTSN001&employeeId=42&fingerId=6'], $this->vftWrites());
    }

    public function test_enroll_face_for_staff(): void
    {
        $registration = $this->onDevice(['member_id_number' => '7', 'device_pin_synced' => '7']);
        config(['vft.mode' => 'off']);
        $staff = $this->staff($registration);
        config(['vft.mode' => 'live']);

        $this->postJson("/api/setup/users/{$staff->id}/enroll", ['type' => 'face'])->assertOk();

        $this->assertSame(['POST /api/devicecmd/enrollface?DevSN=TESTSN001&employeeId=7'], $this->vftWrites());
    }

    public function test_person_must_be_on_the_device_first(): void
    {
        $member = $this->onDevice(['device_pin_synced' => null]);

        $this->postJson("/api/members/{$member->id}/enroll", ['type' => 'face'])
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'not on the door device'));

        Http::assertNothingSent();
    }

    public function test_needs_a_pin_a_finger_and_vft_switched_on(): void
    {
        $member = $this->onDevice();

        $this->postJson("/api/members/{$member->id}/enroll", ['type' => 'finger'])
            ->assertUnprocessable()->assertJsonValidationErrors(['finger_id']);

        config(['vft.mode' => 'off']);
        $this->postJson("/api/members/{$member->id}/enroll", ['type' => 'face'])->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_dry_run_in_log_mode(): void
    {
        $member = $this->onDevice(['device_pin_synced' => null]);
        config(['vft.mode' => 'log']);

        $this->postJson("/api/members/{$member->id}/enroll", ['type' => 'face'])->assertOk()->assertJson(['sent' => false]);

        Http::assertNothingSent();
    }

    public function test_vft_rejection_is_reported(): void
    {
        $this->vftFailure = fn () => Http::response(['message' => 'Cannot find device']);
        $member = $this->onDevice();

        $this->postJson("/api/members/{$member->id}/enroll", ['type' => 'face'])
            ->assertStatus(502)->assertJsonPath('message', fn ($m) => str_contains($m, 'Cannot find device'));
    }
}
