<?php

namespace Tests\Feature\Vft;

use App\Jobs\SyncMemberToDevice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Bus\UniqueLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Vft\Concerns\CreatesGymRecords;
use Tests\TestCase;

/** When the app queues a device push, and PIN validation on the forms. */
class DeviceSyncTriggersTest extends TestCase
{
    use CreatesGymRecords, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function actAsAdmin(): void
    {
        $role = Role::create(['name' => 'Administrator', 'is_admin' => true, 'permissions' => []]);
        $this->actingAs(User::create(['full_name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'x', 'role_id' => $role->id, 'status' => 'Active']), 'sanctum');
    }

    /** Pretend queued pushes already started (releases their unique locks). */
    private function jobStarted(): void
    {
        Queue::pushed(SyncMemberToDevice::class)->each(
            fn ($job) => Cache::lock(UniqueLock::getKey($job))->forceRelease()
        );
    }

    public function test_nothing_is_queued_when_vft_is_off(): void
    {
        config(['vft.mode' => 'off']);
        $member = $this->member(['member_id_number' => '42']);
        $this->pay($member, $this->plan(), '2026-10-10');

        Queue::assertNothingPushed();
        $this->assertNotNull($member->refresh()->access_valid_until, 'access dates are kept even when off');
    }

    public function test_payment_and_member_changes_queue_a_push(): void
    {
        config(['vft.mode' => 'log']);
        $member = $this->member(['member_id_number' => '42']);
        Queue::assertPushed(SyncMemberToDevice::class, 1);
        $this->jobStarted();

        $this->pay($member, $this->plan(), '2026-10-10');
        Queue::assertPushed(SyncMemberToDevice::class, 2);
        $this->jobStarted();

        $member->refresh()->update(['status' => 'Suspended']);
        Queue::assertPushed(SyncMemberToDevice::class, 3);
        $this->jobStarted();

        $member->refresh()->update(['notes' => 'irrelevant to the device']);
        Queue::assertPushed(SyncMemberToDevice::class, 3);
        Queue::assertPushed(SyncMemberToDevice::class, fn ($job) => $job->memberId === $member->id);
    }

    public function test_changes_while_a_push_is_still_queued_collapse_into_it(): void
    {
        config(['vft.mode' => 'log']);
        $member = $this->member(['member_id_number' => '42']);
        $member->update(['full_name' => 'Renamed']);
        $member->update(['status' => 'Suspended']);

        // One queued push; it reads the latest data when it runs.
        Queue::assertPushed(SyncMemberToDevice::class, 1);
    }

    public function test_granting_and_deactivating_staff_queue_a_push(): void
    {
        config(['vft.mode' => 'log']);
        $registration = $this->member(['member_id_number' => '7']);
        Queue::assertPushed(SyncMemberToDevice::class, 1);

        $this->jobStarted();
        $user = $this->staff($registration);
        Queue::assertPushed(SyncMemberToDevice::class, 2);
        $this->jobStarted();

        $user->update(['status' => 'Inactive']);
        Queue::assertPushed(SyncMemberToDevice::class, 3);
    }

    public function test_member_form_requires_numeric_unique_pin(): void
    {
        $this->actAsAdmin();
        $existing = $this->member(['member_id_number' => '42']);
        $other = $this->member(['full_name' => 'Other']);

        $this->putJson("/api/members/{$other->id}", ['member_id_number' => 'CARD-1'])
            ->assertStatus(422)->assertJsonValidationErrors('member_id_number');
        $this->putJson("/api/members/{$other->id}", ['member_id_number' => '1234567890'])
            ->assertStatus(422)->assertJsonValidationErrors('member_id_number');
        $this->putJson("/api/members/{$other->id}", ['member_id_number' => '42'])
            ->assertStatus(422)->assertJsonPath('errors.member_id_number.0', 'That Member ID number is already used by someone else.');

        $this->putJson("/api/members/{$other->id}", ['member_id_number' => '43'])->assertOk();
        $this->putJson("/api/members/{$existing->id}", ['member_id_number' => '42'])->assertOk();
    }

    public function test_grant_role_saves_door_pin_on_registration(): void
    {
        $this->actAsAdmin();
        $registration = $this->member(['username' => 'staffer', 'password' => 'Passw0rd!']);
        $role = Role::create(['name' => 'Front Desk', 'permissions' => []]);

        $this->postJson('/api/setup/users', ['member_id' => $registration->id, 'role_id' => $role->id, 'member_id_number' => '7'])
            ->assertCreated()
            ->assertJsonPath('member_id_number', '7');

        $this->assertSame('7', $registration->refresh()->member_id_number);
    }
}
