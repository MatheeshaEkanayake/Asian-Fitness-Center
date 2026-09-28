<?php

namespace Tests\Feature\Vft;

use App\Services\Vft\MemberDeviceSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Vft\Concerns\CreatesGymRecords;
use Tests\Feature\Vft\Concerns\FakesVft;
use Tests\TestCase;

class MemberDeviceSyncTest extends TestCase
{
    use CreatesGymRecords, FakesVft, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Observers stay quiet (off); the sync itself is exercised in live mode.
        config(['vft.mode' => 'off', 'logging.channels.vft' => ['driver' => 'single', 'path' => storage_path('logs/vft-test.log')]]);
        $this->travelTo('2026-10-15 10:00:00');
        $this->fakeVft();
    }

    private function sync($member): string
    {
        config(['vft.mode' => 'live']);

        return app(MemberDeviceSync::class)->sync($member->refresh());
    }

    public function test_paid_member_is_added_transferred_dated_and_granted(): void
    {
        $member = $this->member(['full_name' => 'Kamal', 'member_id_number' => '42']);
        $this->pay($member, $this->plan('Monthly', 1), '2026-10-10');

        $this->assertSame('synced', $this->sync($member));
        $this->assertSame([
            'POST /api/template/add {"EmpId":"42","EmpName":"Kamal","AreaID":3}',
            'POST /api/template/syncsometoone?DevSN=TESTSN001&empidlist=42',
            'POST /api/devicecmd/validperiod?DevSN=TESTSN001&EmployeeId=42&StartDate=20261010&EndDate=20261109',
            'POST /api/devicecmd/accessgrant?DevSN=TESTSN001&EmployeeId=42',
        ], $this->vftWrites());
        $this->assertSame('42', $member->refresh()->device_pin_synced);
        $this->assertNotNull($member->device_synced_at);
    }

    public function test_renewal_of_member_already_on_device_only_updates_dates(): void
    {
        $this->vftEmployees = [['EmpId' => '42', 'EmpName' => 'Kamal']];
        $member = $this->member(['full_name' => 'Kamal', 'member_id_number' => '42', 'device_pin_synced' => '42',
            'access_valid_from' => '2026-10-01', 'access_valid_until' => '2026-12-31']);

        $this->sync($member);

        $this->assertSame([
            'POST /api/devicecmd/validperiod?DevSN=TESTSN001&EmployeeId=42&StartDate=20261001&EndDate=20261231',
            'POST /api/devicecmd/accessgrant?DevSN=TESTSN001&EmployeeId=42',
        ], $this->vftWrites());
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

    public function test_guest_is_never_put_on_the_device(): void
    {
        $guest = $this->member(['member_id_number' => '42', 'status' => 'Guest',
            'access_valid_from' => '2026-10-01', 'access_valid_until' => '2026-10-31']);

        $this->assertSame('no_access', $this->sync($guest));
        Http::assertNothingSent();
    }

    public function test_expired_member_on_the_device_is_blocked_not_deleted(): void
    {
        $member = $this->member(['member_id_number' => '42', 'device_pin_synced' => '42',
            'access_valid_from' => '2026-09-01', 'access_valid_until' => '2026-09-30']);

        $this->assertSame('synced', $this->sync($member));
        $this->assertSame([
            'POST /api/devicecmd/validperiod?DevSN=TESTSN001&EmployeeId=42&StartDate=20260901&EndDate=20261014',
            'POST /api/devicecmd/accessblock?DevSN=TESTSN001&EmployeeId=42',
        ], $this->vftWrites());
        $this->assertSame('42', $member->refresh()->device_pin_synced, 'still on the device');
    }

    public function test_suspended_member_is_blocked(): void
    {
        $member = $this->member(['member_id_number' => '42', 'device_pin_synced' => '42', 'status' => 'Suspended',
            'access_valid_from' => '2026-10-01', 'access_valid_until' => '2026-12-31']);

        $this->sync($member);

        $this->assertContains('POST /api/devicecmd/accessblock?DevSN=TESTSN001&EmployeeId=42', $this->vftWrites());
    }

    public function test_pin_change_removes_old_person_first(): void
    {
        $member = $this->member(['full_name' => 'Kamal', 'member_id_number' => '1042', 'device_pin_synced' => '42',
            'access_valid_from' => '2026-10-01', 'access_valid_until' => '2026-10-31']);

        $this->sync($member);

        $writes = $this->vftWrites();
        $this->assertSame(['DELETE /api/template/fromdev/42', 'DELETE /api/template/42'], array_slice($writes, 0, 2));
        $this->assertSame('POST /api/template/add {"EmpId":"1042","EmpName":"Kamal","AreaID":3}', $writes[2]);
        $this->assertContains('POST /api/template/syncsometoone?DevSN=TESTSN001&empidlist=1042', $writes);
        $this->assertSame('1042', $member->refresh()->device_pin_synced);
    }

    public function test_removing_pin_removes_the_person(): void
    {
        $member = $this->member(['member_id_number' => null, 'device_pin_synced' => '42']);

        $this->assertSame('no_pin', $this->sync($member));
        $this->assertSame(['DELETE /api/template/fromdev/42', 'DELETE /api/template/42'], $this->vftWrites());
        $this->assertNull($member->refresh()->device_pin_synced);
    }

    public function test_active_staff_get_access_with_no_expiry(): void
    {
        $registration = $this->member(['full_name' => 'Staff One', 'member_id_number' => '7', 'status' => 'Guest']);
        $this->staff($registration);

        $this->sync($registration);

        $this->assertContains('POST /api/devicecmd/validperiod?DevSN=TESTSN001&EmployeeId=7&StartDate=0&EndDate=0', $this->vftWrites());
        $this->assertContains('POST /api/devicecmd/accessgrant?DevSN=TESTSN001&EmployeeId=7', $this->vftWrites());
    }

    public function test_deactivated_staff_are_blocked(): void
    {
        $registration = $this->member(['member_id_number' => '7', 'device_pin_synced' => '7']);
        $this->staff($registration, 'Inactive');

        $this->sync($registration);

        $this->assertContains('POST /api/devicecmd/accessblock?DevSN=TESTSN001&EmployeeId=7', $this->vftWrites());
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

    public function test_failure_reported_with_http_200_is_recorded_on_the_member(): void
    {
        $this->vftFailure = fn (string $call) => str_contains($call, 'accessgrant')
            ? Http::response(['message' => 'Cannot find device'])
            : null;
        $member = $this->member(['member_id_number' => '42',
            'access_valid_from' => '2026-10-01', 'access_valid_until' => '2026-10-31']);

        try {
            $this->sync($member);
            $this->fail('Expected an exception');
        } catch (\App\Services\Vft\VftApiException) {
        }

        $member->refresh();
        $this->assertSame('failed', $member->device_sync_status);
        $this->assertStringContainsString('Cannot find device', $member->device_sync_error);
    }
}
