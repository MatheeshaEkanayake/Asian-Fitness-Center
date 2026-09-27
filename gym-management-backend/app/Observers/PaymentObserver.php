<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\Membership\AccessValidityService;

/**
 * A payment that is (or becomes) Paid extends the member's door access.
 * Updating the member's dates then triggers MemberObserver, which queues
 * the device push. Runs regardless of VFT_MODE — access dates are kept
 * even while the device integration is off.
 */
class PaymentObserver
{
    public function __construct(private readonly AccessValidityService $validity) {}

    public function created(Payment $payment): void
    {
        if ($payment->status === 'Paid') {
            $this->validity->applyPayment($payment);
        }
    }

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('status') && $payment->status === 'Paid') {
            $this->validity->applyPayment($payment);
        }
    }
}
