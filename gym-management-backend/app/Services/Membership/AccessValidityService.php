<?php

namespace App\Services\Membership;

use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentPlan;
use Carbon\CarbonImmutable;

/**
 * Works out the dates a member may enter (members.access_valid_from/until),
 * which the door device then enforces.
 *
 * New paid payment (applyPayment) — extends from the current expiry, so
 * paying early never loses days:
 *   Monthly (N months): starts the day after the current expiry if they're
 *     still covered on the payment date, otherwise on the payment date;
 *     ends N months later minus one day (pay 10 Oct, 1 month → 9 Nov).
 *   Daily: covers the payment date only (never shortens a longer expiry).
 *
 * Backfill (recalculate) — only the latest paid payment counts:
 *   from = its date, until = date + plan length (same month maths).
 *
 * A payment without a plan uses the member's current plan; with neither,
 * access isn't changed and access_note says why.
 */
class AccessValidityService
{
    public function applyPayment(Payment $payment): void
    {
        if ($payment->status !== 'Paid' || ! $payment->member) {
            return;
        }

        $member = $payment->member;
        $plan = $this->planFor($payment, $member);

        if (! $plan) {
            $member->update(['access_note' => $this->noPlanNote($payment)]);

            return;
        }

        $paidOn = CarbonImmutable::parse($payment->date)->startOfDay();
        $until = $member->access_valid_until ? CarbonImmutable::parse($member->access_valid_until) : null;
        $from = $member->access_valid_from ? CarbonImmutable::parse($member->access_valid_from) : null;
        $stillCovered = $until && $until->greaterThanOrEqualTo($paidOn);

        if ($plan->type === 'Daily') {
            if ($stillCovered) {
                // Already covered for that day by a longer plan.
                $member->update(['access_note' => null]);

                return;
            }
            $newFrom = $paidOn;
            $newUntil = $paidOn;
        } else {
            $start = $stillCovered ? $until->addDay() : $paidOn;
            $newFrom = $stillCovered && $from ? $from : $paidOn;
            $newUntil = $this->endOf($start, $plan);
        }

        $member->update([
            'access_valid_from' => $newFrom->toDateString(),
            'access_valid_until' => $newUntil->toDateString(),
            'access_note' => null,
        ]);
    }

    /**
     * Backfill from the latest paid payment only. Returns the new
     * [from, until] (nulls when the member has no usable paid payment).
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function recalculate(Member $member): array
    {
        $payment = $member->payments()
            ->where('status', 'Paid')
            ->reorder()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        $plan = $payment ? $this->planFor($payment, $member) : null;

        if (! $payment || ! $plan) {
            $member->update([
                'access_valid_from' => null,
                'access_valid_until' => null,
                'access_note' => $payment ? $this->noPlanNote($payment) : null,
            ]);

            return [null, null];
        }

        $paidOn = CarbonImmutable::parse($payment->date)->startOfDay();
        $until = $plan->type === 'Daily' ? $paidOn : $this->endOf($paidOn, $plan);

        $member->update([
            'access_valid_from' => $paidOn->toDateString(),
            'access_valid_until' => $until->toDateString(),
            'access_note' => null,
        ]);

        return [$paidOn->toDateString(), $until->toDateString()];
    }

    private function planFor(Payment $payment, Member $member): ?PaymentPlan
    {
        return $payment->paymentPlan ?? $member->paymentPlan;
    }

    private function endOf(CarbonImmutable $start, PaymentPlan $plan): CarbonImmutable
    {
        return $start->addMonthsNoOverflow(max(1, (int) $plan->months))->subDay();
    }

    private function noPlanNote(Payment $payment): string
    {
        return "Payment {$payment->invoice_number} has no payment plan and the member has none either, so door access wasn't extended.";
    }
}
