<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

/**
 * PaymentController — handles recording and updating payments.
 *
 * NOTE: renamed from TransactionController (table `transactions` → `payments`,
 * model Transaction → Payment). Routes moved from /api/transactions/* to
 * /api/payments/* — see routes/api.php. The frontend's paymentService.js
 * keeps its existing function/variable names (transaction, transactions,
 * etc.) unchanged; only the endpoint paths it calls were updated.
 *
 * This controller replaces the following functions from the React frontend:
 *
 *   Frontend file: src/services/paymentService.js
 *   ┌──────────────────────────────────────┬──────────────────────────────────────────┐
 *   │ Frontend function                    │ Backend endpoint                         │
 *   ├──────────────────────────────────────┼──────────────────────────────────────────┤
 *   │ listTransactions()                   │ GET  /api/payments                       │
 *   │ getTransaction(transactionId)        │ GET  /api/payments/{id}                  │
 *   │ listTransactionsForMember(memberId)  │ GET  /api/members/{id}/payments          │
 *   │                                      │      (handled in MemberController)       │
 *   │ recordPayment(input)                 │ POST /api/payments                       │
 *   │ markTransactionPaid(transactionId)   │ PUT  /api/payments/{id}/mark-paid        │
 *   └──────────────────────────────────────┴──────────────────────────────────────────┘
 *
 *   Frontend context: src/context/PaymentsContext.jsx
 *   → recordPayment(), markTransactionPaid(), getTransactionById(), getTransactionsForMember()
 *
 *   Frontend pages that consume this:
 *   → src/pages/payments/TransactionsListPage.jsx   (GET /api/payments)
 *   → src/pages/payments/TransactionFormPage.jsx    (POST /api/payments)
 *   → src/pages/payments/TransactionDetailPage.jsx  (GET /{id}, PUT /{id}/mark-paid)
 *   → src/pages/payments/PaymentsDashboardPage.jsx  (GET /api/payments — recent 6)
 */
class PaymentController extends Controller
{
    /**
     * GET /api/payments
     *
     * List all payments sorted by date descending.
     * Replaces: paymentService.js → listTransactions()
     *
     * Query params (mirror TransactionsListPage.jsx filter/search state):
     *   ?search={string}     — filters by invoice number or member name
     *   ?status={string}     — Paid | Pending | Failed | Refunded | PartiallyRefunded
     *   ?method={string}     — Cash | Card | Bank Transfer | Online
     *   ?type={string}       — OneTime | Recurring
     *   ?member_id={int}
     *   ?per_page={int}      — default: 50
     */
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $query = Payment::with('member')->orderByDesc('date');

        if ($memberId = $request->input('member_id')) {
            $query->where('member_id', $memberId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($method = $request->input('method')) {
            $query->where('method', $method);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Search by invoice number or member name (mirrors TransactionsListPage.jsx query state)
        if ($search = $request->input('search')) {
            $q = strtolower($search);
            $query->where(function ($builder) use ($q) {
                $builder->whereRaw('LOWER(invoice_number) LIKE ?', ["%{$q}%"])
                        ->orWhereHas('member', function ($mb) use ($q) {
                            $mb->whereRaw('LOWER(full_name) LIKE ?', ["%{$q}%"]);
                        });
            });
        }

        $payments = $query->paginate($request->input('per_page', 50));

        return response()->json($payments);
    }

    /**
     * GET /api/payments/{id}
     *
     * Fetch a single payment with its related member.
     * Replaces: paymentService.js → getTransaction(transactionId)
     */
    public function show(Payment $payment): JsonResponse
    {
        return response()->json($payment->load('member'));
    }

    /**
     * POST /api/payments
     *
     * Record a new payment.
     * Replaces: paymentService.js → recordPayment(input)
     *
     * Invoice number is generated server-side here, replacing the
     * nextInvoiceNumber() counter function in paymentService.js.
     *
     * Default values (date = today, status = Paid) mirror createMember defaults.
     *
     * Validation rules: app/Http/Requests/StorePaymentRequest.php
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Generate invoice number server-side
        // Replaces: paymentService.js → nextInvoiceNumber()
        $lastInvoice = Payment::orderByDesc('id')->value('invoice_number');
        $lastNumber  = $lastInvoice ? (int) str_replace('INV-', '', $lastInvoice) : 3010;
        $invoiceNumber = 'INV-' . ($lastNumber + 1);

        $payment = Payment::create([
            'date'           => now()->toDateString(),
            'status'         => 'Paid',
            'invoice_number' => $invoiceNumber,
            ...$data,
            // If Pending and no due_date provided, fall back to the payment date
            'due_date' => $data['due_date'] ?? $data['date'] ?? now()->toDateString(),
        ]);

        return response()->json($payment->load('member'), 201);
    }

    /**
     * PUT /api/payments/{id}/mark-paid
     *
     * Mark a pending payment as paid, updating the payment date to today.
     * Replaces: paymentService.js → markTransactionPaid(transactionId)
     */
    public function markPaid(Payment $payment): JsonResponse
    {
        $payment->update([
            'status' => 'Paid',
            'date'   => now()->toDateString(),
        ]);

        return response()->json($payment->fresh()->load('member'));
    }
}
