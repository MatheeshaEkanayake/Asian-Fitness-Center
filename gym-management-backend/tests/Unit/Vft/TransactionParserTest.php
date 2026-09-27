<?php

namespace Tests\Unit\Vft;

use App\Services\Vft\TransactionParser;
use Tests\TestCase;

class TransactionParserTest extends TestCase
{
    private function encode(int $y, int $mo, int $d, int $h, int $mi, int $s): int
    {
        return (((((($y - 2000) * 12 + $mo - 1) * 31 + $d - 1) * 24 + $h) * 60 + $mi) * 60) + $s;
    }

    public function test_decodes_zkteco_time_second(): void
    {
        $time = (new TransactionParser)->decodeTimeSecond($this->encode(2026, 9, 26, 6, 30, 15), 'Asia/Colombo');

        $this->assertSame('2026-09-26 06:30:15', $time->format('Y-m-d H:i:s'));
    }

    public function test_parses_zkteco_text_rows(): void
    {
        $text = "transaction cardno=0\tpin=42\tverified=1\tdoorid=1\teventtype=0\tinoutstate=0\ttime_second=".$this->encode(2026, 9, 26, 6, 30, 0)
            ."\ntransaction cardno=0\tpin=43\teventtype=27\ttime_second=".$this->encode(2026, 9, 26, 7, 0, 0)."\n";

        $punches = (new TransactionParser)->parse($text);

        $this->assertCount(2, $punches);
        $this->assertSame('42', $punches[0]['pin']);
        $this->assertSame('0', $punches[0]['event_type']);
        $this->assertSame('2026-09-26 06:30:00', $punches[0]['punched_at']->format('Y-m-d H:i:s'));
        $this->assertSame('27', $punches[1]['event_type']);
    }

    public function test_parses_json_rows_wrapped_in_data(): void
    {
        $punches = (new TransactionParser)->parse(['data' => [
            ['Pin' => '42', 'Time' => '2026-09-26 18:05:00', 'EventType' => 0],
            ['Pin' => '', 'Time' => '2026-09-26 18:06:00'],
            ['Pin' => '9', 'Time' => 'not a date'],
        ]]);

        $this->assertCount(1, $punches);
        $this->assertSame('42', $punches[0]['pin']);
        $this->assertSame('18:05', $punches[0]['punched_at']->format('H:i'));
    }

    public function test_unknown_or_queued_responses_give_no_punches(): void
    {
        $parser = new TransactionParser;

        $this->assertSame([], $parser->parse(['success' => 'command queued']));
        $this->assertSame([], $parser->parse('OK'));
        $this->assertSame([], $parser->parse(null));
    }
}
