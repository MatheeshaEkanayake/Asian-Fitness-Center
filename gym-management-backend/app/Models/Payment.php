<?php

namespace App\Models;

use App\Observers\PaymentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment Eloquent model.
 *
 * NOTE: this model/table was originally named Transaction/`transactions` —
 * renamed to Payment/`payments` to match "payment" domain terminology.
 * The frontend's paymentService.js keeps its existing function/variable
 * names (transaction, transactions, etc.) unchanged; only the API paths it
 * calls moved from /api/transactions to /api/payments.
 *
 * Replaces the in-memory transactions array in:
 *   Frontend: src/services/db.js → db.transactions
 *
 * Business logic from the following frontend files now lives in
 * PaymentController:
 *   - listTransactions()           → GET  /api/payments
 *   - getTransaction()             → GET  /api/payments/{id}
 *   - listTransactionsForMember()  → GET  /api/members/{id}/payments
 *   - recordPayment()              → POST /api/payments
 *   - markTransactionPaid()        → PUT  /api/payments/{id}/mark-paid
 *
 * Status values: Paid | Pending | Failed | Refunded | PartiallyRefunded
 * Type values:   OneTime | Recurring
 * Method values: Cash | Card | Bank Transfer | Online
 *
 * Invoice numbers are generated server-side in PaymentController::store()
 * replacing the nextInvoiceNumber() counter in paymentService.js.
 *
 * NOTE: the refund-issuing feature (Refund model, RefundController, the
 * refunds table) was removed entirely — Refunded/PartiallyRefunded remain
 * valid status strings, but nothing currently sets them.
 */
#[ObservedBy(PaymentObserver::class)]
class Payment extends Model
{
    protected $fillable = [
        'member_id',
        // Copy of who paid, filled when the member is purged
        // (members:purge-archived) so the payment still shows them.
        'member_name',
        'member_id_number',
        'member_phone',
        'payment_plan_id',
        'amount',
        'method',
        'type',
        'status',
        'date',
        'due_date',
        'invoice_number',
        'notes',
    ];

    // Always include the plan (sent to the frontend as `paymentPlan`).
    protected $with = ['paymentPlan'];

    protected $casts = [
        'date'     => 'date',
        'due_date' => 'date',
        'amount'   => 'decimal:2',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * The member who made this payment, including archived members so
     * payment details still show them. Null once the member is purged —
     * see member_name / member_id_number / member_phone.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /**
     * The plan this payment was made for, recorded at payment time.
     */
    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }
}
