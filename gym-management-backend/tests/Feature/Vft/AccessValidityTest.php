<?php

namespace Tests\Feature\Vft;

use App\Services\Membership\AccessValidityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Vft\Concerns\CreatesGymRecords;
use Tests\TestCase;

class AccessValidityTest extends TestCase
{
    use CreatesGymRecords, RefreshDatabase;

    public function test_monthly_payment_covers_n_months_from_payment_date(): void
    {
        $member = $this->member();
        $this->pay($member, $this->plan('Monthly', 1), '2026-10-10');

        $member->refresh();
        $this->assertSame('2026-10-10', $member->access_valid_from->toDateString());
        $this->assertSame('2026-11-09', $member->access_valid_until->toDateString());
    }

    public function test_paying_early_extends_from_current_expiry(): void
    {
        $member = $this->member();
        $plan = $this->plan('Monthly', 1);
        $this->pay($member, $plan, '2026-10-10');  // → 9 Nov
        $this->pay($member, $plan, '2026-11-01');  // still covered → 10 Nov .. 9 Dec

        $member->refresh();
        $this->assertSame('2026-10-10', $member->access_valid_from->toDateString());
        $this->assertSame('2026-12-09', $member->access_valid_until->toDateString());
    }

    public function test_paying_after_expiry_starts_again_from_payment_date(): void
    {
        $member = $this->member();
        $plan = $this->plan('Monthly', 3);
        $this->pay($member, $plan, '2026-01-15');  // → 14 Apr
        $this->pay($member, $plan, '2026-06-01');  // lapsed → 1 Jun .. 31 Aug

        $member->refresh();
        $this->assertSame('2026-06-01', $member->access_valid_from->toDateString());
        $this->assertSame('2026-08-31', $member->access_valid_until->toDateString());
    }

    public function test_month_end_does_not_overflow(): void
    {
        $member = $this->member();
        $this->pay($member, $this->plan('Monthly', 1), '2026-01-31');

        $this->assertSame('2026-02-27', $member->refresh()->access_valid_until->toDateString());
    }

    public function test_daily_plan_covers_that_day_only_and_never_shortens(): void
    {
        $member = $this->member();
        $this->pay($member, $this->plan('Daily'), '2026-10-10');
        $this->assertSame('2026-10-10', $member->refresh()->access_valid_until->toDateString());

        $this->pay($member, $this->plan('Monthly', 1), '2026-10-11'); // → 10 Nov
        $this->pay($member, $this->plan('Daily'), '2026-10-20');      // already covered
        $this->assertSame('2026-11-10', $member->refresh()->access_valid_until->toDateString());
    }

    public function test_pending_payment_gives_no_access_until_marked_paid(): void
    {
        $member = $this->member();
        $payment = $this->pay($member, $this->plan('Monthly', 1), '2026-10-10', 'Pending');
        $this->assertNull($member->refresh()->access_valid_until);

        $payment->update(['status' => 'Paid', 'date' => '2026-10-12']);
        $this->assertSame('2026-11-11', $member->refresh()->access_valid_until->toDateString());
    }

    public function test_payment_without_plan_uses_members_plan(): void
    {
        $member = $this->member(['payment_plan_id' => $this->plan('Monthly', 2)->id]);
        $this->pay($member, null, '2026-10-10');

        $this->assertSame('2026-12-09', $member->refresh()->access_valid_until->toDateString());
    }

    public function test_payment_without_any_plan_leaves_access_and_explains(): void
    {
        $member = $this->member();
        $payment = $this->pay($member, null, '2026-10-10');

        $member->refresh();
        $this->assertNull($member->access_valid_until);
        $this->assertStringContainsString($payment->invoice_number, $member->access_note);
    }

    public function test_recalculate_uses_latest_paid_payment_only(): void
    {
        $member = $this->member();
        $plan = $this->plan('Monthly', 1);
        $this->pay($member, $plan, '2026-08-01');
        $this->pay($member, $this->plan('Monthly', 3), '2026-09-01');
        $this->pay($member, $plan, '2026-10-01', 'Pending');
        $member->update(['access_valid_from' => null, 'access_valid_until' => null]);

        [$from, $until] = app(AccessValidityService::class)->recalculate($member);

        $this->assertSame(['2026-09-01', '2026-11-30'], [$from, $until]);
    }
}
