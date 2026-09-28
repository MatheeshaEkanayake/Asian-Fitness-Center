<?php

namespace Tests\Unit\Vft;

use App\Services\Vft\WebhookPunchParser;
use Tests\TestCase;

class WebhookPunchParserTest extends TestCase
{
    public function test_parses_the_collections_webhook_format(): void
    {
        $punches = (new WebhookPunchParser)->parse([
            ['EmpId' => '168', 'AttTime' => '2024-01-15 08:30:15', 'CheckingStatus' => '0', 'VerifyType' => '1', 'Event' => '3', 'DeviceID' => 'QEM1253800017'],
        ]);

        $this->assertCount(1, $punches);
        $this->assertSame('168', $punches[0]['pin']);
        $this->assertSame('QEM1253800017', $punches[0]['device_sn']);
        $this->assertSame('3', $punches[0]['event_type']);
        $this->assertSame('2024-01-15 08:30:15', $punches[0]['punched_at']->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Colombo', $punches[0]['punched_at']->getTimezone()->getName());
        $this->assertSame('1', $punches[0]['raw']['VerifyType']);
    }

    public function test_accepts_a_single_object_and_numeric_emp_ids(): void
    {
        $punches = (new WebhookPunchParser)->parse(['EmpId' => 42, 'AttTime' => '2026-10-15 06:30:00']);

        $this->assertSame('42', $punches[0]['pin']);
        $this->assertSame('TESTSN001', $punches[0]['device_sn'], 'falls back to the default device');
        $this->assertNull($punches[0]['event_type']);
    }

    public function test_skips_rows_without_emp_id_or_readable_time(): void
    {
        $punches = (new WebhookPunchParser)->parse([
            ['AttTime' => '2026-10-15 06:30:00'],
            ['EmpId' => '42', 'AttTime' => 'not a time'],
            ['EmpId' => '42'],
            'junk',
        ]);

        $this->assertSame([], $punches);
        $this->assertSame([], (new WebhookPunchParser)->parse('not json'));
    }
}
