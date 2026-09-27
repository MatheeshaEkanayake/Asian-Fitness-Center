<?php

namespace Tests\Feature\Vft;

use App\Services\Vft\MemberDeviceSync;
use App\Services\Vft\VftApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Vft\Concerns\CreatesGymRecords;
use Tests\TestCase;

class MemberDeviceSyncTest extends TestCase
{
    use CreatesGymRecords, RefreshDatabase;

    /** What the fake VFT returns for device commands (null = success). */
    private $deviceResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Observers stay quiet (off); the sync itself is exercised in live mode.
        config(['vft.mode' => 'off', 'logging.channels.vft' => ['driver' => 'single', 'path' => storage_path('logs/vft-test.log')]]);
        $this->travelTo('2026-10-15 10:00:00');
        Cache::put(VftApiClient::TOKEN_CACHE_KEY, 'test-token', 600);
        Http::fake(['vft.test/api/devicecmd' => fn () => $this->deviceResponse ?? Http::response(['success' => 'queued'])]);
    }

    private function sync($member): string
    {
        config(['vft.mode' => 'live']);

        return app(MemberDeviceSync::class)->sync($member->refresh());
    }

    /** @return string[] */
    private function commands(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0]['Content'])->values()->all();
    }

    public function test_paid_member_is_added_with_dates_and_door_access(): void
    {
        $member = $this->member(['full_name' => 'Kamal', 'member_id_number' => '42']);
        $this->pay($member, $this->plan('Monthly', 1), '2026-10-10');

        $this->assertSame('synced', $this->sync($member));
        $this->assertSame([
            "DATA UPDATE user CardNo=\tPin=42\tPassword=\tGroup=1\tStartTime=20261010\tEndTime=20261109\tName=Kamal\tPrivilege=0\tDisable=0",
            "DATA UPDATE userauthorize Pin=42\tAuthorizeTimezoneId=1\tAuthorizeDoorId=1",
        ], $this->commands());
        $this->assertSame('42', $member->refresh()->device_pin_synced);
        $this->assertNotNull($member->device_synced_at);
    }

    public function test_member_without_pin_is_skipped(): void
    {
        $member = $this->member(['member_id_number' => 'CARD-0042']);

        $this->assertSame('no_pin', $this->sync($member));
        Http::assertNothingSent();
    }

    public function test_never_synced_member_without_access_sends_nothing(): void
    {
        $member = $this->member(['member_id_number' => '42']);

        $this->assertSame('no_access', $this->sync($member));
        Http::assertNothingSent();
    }

    public function test_expired_member_on_the_device_is_blocked_not_deleted(): void
    {
        $member = $this->member(['member_id_number' => '42', 'device_pin_synced' => '42',
            'access_valid_from' => '2026-09-01', 'access_valid_until' => '2026-09-30']);

        $this->assertSame('synced', $this->sync($member));
        $this->assertSame([
            "DATA UPDATE user Pin=42\tStartTime=20260901\tEndTime=20261014",
            'DATA DELETE userauthorize Pin=42',
        ], $this->commands());
        $this->assertSame('42', $member->refresh()->device_pin_synced, 'still on the device');
    }

    public function test_suspended_member_is_blocked(): void
    {
        $member = $this->member(['member_id_number' => '42', 'device_pin_synced' => '42', 'status' => 'Suspended',
            'access_valid_from' => '2026-10-01', 'access_valid_until' => '2026-12-31']);

        $this->sync($member);

        $this->assertContains('DATA DELETE userauthorize Pin=42', $this->commands());
    }

    public function test_pin_change_deletes_old_device_user_first(): void
    {
        $member = $this->member(['member_id_number' => '1042', 'device_pin_synced' => '42',
            'access_valid_from' => '2026-10-01', 'access_valid_until' => '2026-10-31']);

        $this->sync($member);

        $commands = $this->commands();
        $this->assertSame([
            'DATA DELETE userauthorize Pin=42',
            'DATA DELETE templatev10 Pin=42',
            'DATA DELETE user Pin=42',
        ], array_slice($commands, 0, 3));
        $this->assertStringContainsString("Pin=1042\t", $commands[3]);
        $this->assertSame('1042', $member->refresh()->device_pin_synced);
    }

    public function test_removing_pin_deletes_device_user(): void
    {
        $member = $this->member(['member_id_number' => null, 'device_pin_synced' => '42']);

        $this->assertSame('no_pin', $this->sync($member));
        $this->assertContains('DATA DELETE user Pin=42', $this->commands());
        $this->assertNull($member->refresh()->device_pin_synced);
    }

    public function test_active_staff_get_access_with_no_expiry(): void
    {
        $registration = $this->member(['full_name' => 'Staff One', 'member_id_number' => '7']);
        $this->staff($registration);

        $this->sync($registration);

        $this->assertStringContainsString("Pin=7\tPassword=\tGroup=1\tStartTime=0\tEndTime=0", $this->commands()[0]);
    }

    public function test_deactivated_staff_are_blocked(): void
    {
        $registration = $this->member(['member_id_number' => '7', 'device_pin_synced' => '7']);
        $this->staff($registration, 'Inactive');

        $this->sync($registration);

        $this->assertContains('DATA DELETE userauthorize Pin=7', $this->commands());
    }

    public function test_dry_run_records_status_without_claiming_device_state(): void
    {
        config(['vft.mode' => 'log']);
        $member = $this->member(['member_id_number' => '42',
            'access_valid_from' => '2026-10-01', 'access_valid_until' => '2026-10-31']);

        $status = app(MemberDeviceSync::class)->sync($member->refresh());

        $this->assertSame('dry_run', $status);
        Http::assertNothingSent();
        $this->assertNull($member->refresh()->device_pin_synced);
    }

    public function test_failure_is_recorded_on_the_member(): void
    {
        $this->deviceResponse = Http::response('boom', 500);
        $member = $this->member(['member_id_number' => '42',
            'access_valid_from' => '2026-10-01', 'access_valid_until' => '2026-10-31']);

        try {
            $this->sync($member);
            $this->fail('Expected an exception');
        } catch (\App\Services\Vft\VftApiException) {
        }

        $member->refresh();
        $this->assertSame('failed', $member->device_sync_status);
        $this->assertStringContainsString('HTTP 500', $member->device_sync_error);
    }
}
