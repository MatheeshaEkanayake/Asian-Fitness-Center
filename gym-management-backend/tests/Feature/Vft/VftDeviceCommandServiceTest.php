<?php

namespace Tests\Feature\Vft;

use App\Services\Vft\VftApiClient;
use App\Services\Vft\VftDeviceCommandService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class VftDeviceCommandServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['vft.mode' => 'live', 'logging.channels.vft' => ['driver' => 'single', 'path' => storage_path('logs/vft-test.log')]]);
        Cache::put(VftApiClient::TOKEN_CACHE_KEY, 'test-token', 600);
        Http::fake(['vft.test/api/devicecmd' => Http::response(['success' => 'queued'])]);
    }

    private function service(): VftDeviceCommandService
    {
        return app(VftDeviceCommandService::class);
    }

    /** The Content of the last command sent. */
    private function sentContent(): string
    {
        $last = Http::recorded()->last()[0];
        $this->assertSame('https://vft.test/api/devicecmd', $last->url());
        $this->assertSame('TESTSN001', $last['DevSN']);
        $this->assertSame('General', $last['Type']);

        return $last['Content'];
    }

    public function test_add_or_update_member_builds_tab_separated_user_command(): void
    {
        $result = $this->service()->addOrUpdateMember('1042', 'Kamal Perera',
            CarbonImmutable::parse('2026-10-10'), CarbonImmutable::parse('2026-11-09'));

        $this->assertTrue($result->sent);
        $this->assertSame(['success' => 'queued'], $result->response);
        $this->assertSame(
            "DATA UPDATE user CardNo=\tPin=1042\tPassword=\tGroup=1\tStartTime=20261010\tEndTime=20261109\tName=Kamal Perera\tPrivilege=0\tDisable=0",
            $this->sentContent(),
        );
    }

    public function test_no_expiry_is_sent_as_zero_dates(): void
    {
        $this->service()->addOrUpdateMember('7', 'Staff One', null, null);

        $this->assertStringContainsString("StartTime=0\tEndTime=0", $this->sentContent());
    }

    public function test_tabs_and_newlines_in_names_cannot_inject_fields(): void
    {
        $this->service()->addOrUpdateMember('8', "Evil\tPrivilege=14\nDATA DELETE user Pin=1", null, null);

        $content = $this->sentContent();
        $this->assertSame(9, count(explode("\t", $content)), 'still exactly 9 fields');
        $this->assertStringNotContainsString("\n", $content);
        $this->assertStringContainsString("\tPrivilege=0\t", $content);
        // Control chars become spaces, then the name is cut to 24 characters.
        $this->assertStringContainsString("\tName=Evil Privilege=14 DATA D\t", $content);
    }

    public function test_long_names_are_truncated(): void
    {
        $this->service()->addOrUpdateMember('8', str_repeat('A', 40), null, null);

        $this->assertStringContainsString('Name='.str_repeat('A', 24)."\t", $this->sentContent());
    }

    public function test_set_member_validity(): void
    {
        $this->service()->setMemberValidity('8', CarbonImmutable::parse('2024-10-10'), CarbonImmutable::parse('2025-10-20'));

        $this->assertSame("DATA UPDATE user Pin=8\tStartTime=20241010\tEndTime=20251020", $this->sentContent());
    }

    public function test_grant_and_revoke_door_access(): void
    {
        $this->service()->grantDoorAccess('8');
        $this->assertSame("DATA UPDATE userauthorize Pin=8\tAuthorizeTimezoneId=1\tAuthorizeDoorId=1", $this->sentContent());

        $this->service()->grantDoorAccess('8', doorId: 2, timezoneId: 3);
        $this->assertSame("DATA UPDATE userauthorize Pin=8\tAuthorizeTimezoneId=3\tAuthorizeDoorId=2", $this->sentContent());

        $this->service()->revokeDoorAccess('8');
        $this->assertSame('DATA DELETE userauthorize Pin=8', $this->sentContent());
    }

    public function test_delete_member_removes_access_fingerprints_and_user(): void
    {
        $this->service()->deleteMember('42');

        $this->assertSame([
            'DATA DELETE userauthorize Pin=42',
            'DATA DELETE templatev10 Pin=42',
            'DATA DELETE user Pin=42',
        ], Http::recorded()->map(fn ($pair) => $pair[0]['Content'])->all());
    }

    public function test_open_door_command(): void
    {
        $this->service()->openDoor();
        $this->assertSame('CONTROL DEVICE 01010105', $this->sentContent());

        $this->service()->openDoor(2, 10);
        $this->assertSame('CONTROL DEVICE 01020110', $this->sentContent());
    }

    public function test_queries(): void
    {
        $this->service()->getUsers();
        $this->assertSame('DATA QUERY tablename=user,fielddesc=*,filter=*', $this->sentContent());

        $this->service()->getFingerprintTemplates();
        $this->assertSame('DATA QUERY tablename=templatev10,fielddesc=*,filter=*', $this->sentContent());

        $this->service()->getTransactions();
        $this->assertSame('DATA QUERY tablename=transaction,fielddesc=*,filter=*', $this->sentContent());
    }

    public function test_rejects_invalid_pins(): void
    {
        foreach (['', 'CARD-0042', '12 3', '1234567890', "8\tPin=9"] as $pin) {
            try {
                $this->service()->revokeDoorAccess($pin);
                $this->fail("PIN \"{$pin}\" should be rejected");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        Http::assertNothingSent();
    }

    public function test_rejects_bad_door_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->openDoor(1, 0);
    }

    public function test_log_mode_builds_but_does_not_send(): void
    {
        config(['vft.mode' => 'log']);

        $result = $this->service()->grantDoorAccess('8');

        $this->assertFalse($result->sent);
        $this->assertSame('log', $result->mode);
        $this->assertStringStartsWith('DATA UPDATE userauthorize', $result->content);
        Http::assertNothingSent();
    }

    public function test_off_mode_sends_nothing(): void
    {
        config(['vft.mode' => 'off']);

        $this->assertFalse($this->service()->openDoor()->sent);
        Http::assertNothingSent();
    }

    public function test_requires_a_device_serial(): void
    {
        config(['vft.default_device_sn' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->openDoor();
    }
}
