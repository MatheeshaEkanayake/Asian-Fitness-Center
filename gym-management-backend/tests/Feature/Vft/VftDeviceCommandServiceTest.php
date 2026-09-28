<?php

namespace Tests\Feature\Vft;

use App\Services\Vft\VftDeviceCommandService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\Feature\Vft\Concerns\FakesVft;
use Tests\TestCase;

class VftDeviceCommandServiceTest extends TestCase
{
    use FakesVft;

    protected function setUp(): void
    {
        parent::setUp();

        config(['vft.mode' => 'live', 'logging.channels.vft' => ['driver' => 'single', 'path' => storage_path('logs/vft-test.log')]]);
        $this->fakeVft();
    }

    private function commands(): VftDeviceCommandService
    {
        return app(VftDeviceCommandService::class);
    }

    public function test_new_person_is_added_to_the_default_devices_area(): void
    {
        $this->commands()->upsertPerson('42', 'Kamal Perera');

        $this->assertSame(['POST /api/template/add {"EmpId":"42","EmpName":"Kamal Perera","AreaID":3}'], $this->vftWrites());
    }

    public function test_existing_person_is_renamed_not_added_again(): void
    {
        $this->vftEmployees = [['EmpId' => 42, 'EmpName' => 'Old Name']];

        $this->commands()->upsertPerson('42', 'Kamal Perera');

        $this->assertSame(['PUT /api/template/edit/42 {"EmpName":"Kamal Perera"}'], $this->vftWrites());
    }

    public function test_unchanged_person_sends_nothing(): void
    {
        $this->vftEmployees = [['EmpId' => '42', 'EmpName' => 'Kamal Perera']];

        $this->commands()->upsertPerson('42', 'Kamal Perera');

        $this->assertSame([], $this->vftWrites());
    }

    public function test_area_lookup_is_cached(): void
    {
        $this->commands()->upsertPerson('42', 'A');
        $this->commands()->upsertPerson('43', 'B');

        $deviceLookups = Http::recorded(fn ($request) => str_ends_with($request->url(), '/api/device'));
        $this->assertCount(1, $deviceLookups);
    }

    public function test_unregistered_device_is_an_error(): void
    {
        config(['vft.default_device_sn' => 'OTHERSN']);

        $this->expectExceptionMessage('Device OTHERSN is not registered');

        $this->commands()->upsertPerson('42', 'Kamal');
    }

    public function test_control_characters_in_names_are_stripped_and_long_names_truncated(): void
    {
        $this->commands()->upsertPerson('42', "Kamal\tPerera\nWith A Very Long Surname Indeed");

        $this->assertStringContainsString('"EmpName":"Kamal Perera With A Very"', $this->vftWrites()[0]);
    }

    public function test_validity_transfer_grant_and_block(): void
    {
        $service = $this->commands();
        $service->transferToDevice('42');
        $service->setValidity('42', CarbonImmutable::parse('2026-10-10'), CarbonImmutable::parse('2026-11-09'));
        $service->setValidity('7', null, null);
        $service->grantAccess('42');
        $service->blockAccess('42');

        $this->assertSame([
            'POST /api/template/syncsometoone?DevSN=TESTSN001&empidlist=42',
            'POST /api/devicecmd/validperiod?DevSN=TESTSN001&EmployeeId=42&StartDate=20261010&EndDate=20261109',
            'POST /api/devicecmd/validperiod?DevSN=TESTSN001&EmployeeId=7&StartDate=0&EndDate=0',
            'POST /api/devicecmd/accessgrant?DevSN=TESTSN001&EmployeeId=42',
            'POST /api/devicecmd/accessblock?DevSN=TESTSN001&EmployeeId=42',
        ], $this->vftWrites());
    }

    public function test_remove_person_deletes_from_devices_then_cloud(): void
    {
        $this->commands()->removePerson('42');

        $this->assertSame(['DELETE /api/template/fromdev/42', 'DELETE /api/template/42'], $this->vftWrites());
    }

    public function test_enrollment_and_door(): void
    {
        $service = $this->commands();
        $service->enrollFinger('42', 6);
        $service->enrollFace('42');
        $service->openDoor();

        $this->assertSame([
            'POST /api/devicecmd/enrollfinger?DevSN=TESTSN001&employeeId=42&fingerId=6',
            'POST /api/devicecmd/enrollface?DevSN=TESTSN001&employeeId=42',
            'POST /api/devicecmd/dooropen?DevSN=TESTSN001',
        ], $this->vftWrites());
    }

    public function test_rejects_invalid_pins_and_fingers(): void
    {
        foreach (['', 'abc', '12 34', '1234567890', "42\tX"] as $pin) {
            try {
                $this->commands()->grantAccess($pin);
                $this->fail("PIN \"{$pin}\" should be rejected");
            } catch (InvalidArgumentException) {
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->commands()->enrollFinger('42', 10);
    }

    public function test_log_mode_logs_but_sends_nothing(): void
    {
        config(['vft.mode' => 'log']);

        $result = $this->commands()->setValidity('42', CarbonImmutable::parse('2026-10-10'), null);

        $this->assertFalse($result->sent);
        $this->assertSame('validperiod EmployeeId=42 StartDate=20261010 EndDate=0', $result->describe());
        Http::assertNothingSent();
    }

    public function test_off_mode_sends_nothing(): void
    {
        config(['vft.mode' => 'off']);

        $this->commands()->upsertPerson('42', 'Kamal');
        $this->commands()->grantAccess('42');

        Http::assertNothingSent();
    }

    public function test_requires_a_device_serial(): void
    {
        config(['vft.default_device_sn' => '']);

        $this->expectExceptionMessage('VFT_DEFAULT_DEVICE_SN');

        $this->commands()->openDoor();
    }
}
