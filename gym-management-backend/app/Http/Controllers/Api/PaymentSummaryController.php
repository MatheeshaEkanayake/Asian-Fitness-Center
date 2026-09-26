<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

/**
 * PaymentSummaryController — returns aggregated payment statistics for the dashboard.
 *
 * This controller replaces the following function from the React frontend:
 *
 *   Frontend file: src/services/paymentService.js
 *   ┌───────────────────────┬────────────────────────────┐
 *   │ Frontend function     │ Backend endpoint           │
 *   ├───────────────────────┼────────────────────────────┤
 *   │ getPaymentSummary()   │ GET /api/payments/summary  │
 *   └───────────────────────┴────────────────────────────┘
 *
 *   Frontend context: src/context/PaymentsContext.jsx → refreshSummary()
 *   Frontend pages that consume this:
 *   → src/pages/payments/PaymentsDashboardPage.jsx (stats cards)
 *   → src/pages/DashboardPage.jsx (revenue this month, pending count)
 *
 * Response shape matches the frontend summary object exactly:
 *   { revenueThisMonth, pendingCount, overdueCount, failedCount }
 */
class PaymentSummaryController extends Controller
{
    /**
     * GET /api/payments/summary
     *
     * Returns the same 5-field summary object that getPaymentSummary()
     * returned in the frontend. All calculations that were done in JS
     * (filtering db.transactions with Array.filter + reduce) are now
     * done here with Eloquent aggregate queries.
     */
    public function __invoke(): JsonResponse
    {
        $now       = now();
        $thisMonth = $now->month;
        $thisYear  = $now->year;

        // Revenue this month: sum of Paid payments in the current calendar month
        // Mirrors: db.transactions.filter(t => t.status === 'Paid' && isThisMonth(t.date))
        //                         .reduce((sum, t) => sum + Number(t.amount), 0)
        $revenueThisMonth = Payment::query()
            ->where('status', 'Paid')
            ->whereMonth('date', $thisMonth)
            ->whereYear('date', $thisYear)
            ->sum('amount');

        // Pending count: all payments with status === 'Pending'
        // Mirrors: db.transactions.filter(t => t.status === 'Pending').length
        $pendingCount = Payment::where('status', 'Pending')->count();

        // Overdue count: Pending payments whose due_date is in the past
        // Mirrors: db.transactions.filter(t => t.status === 'Pending' && new Date(t.dueDate) < now)
        $overdueCount = Payment::query()
            ->where('status', 'Pending')
            ->where('due_date', '<', $now->toDateString())
            ->count();

        // Failed count: all payments with status === 'Failed'
        // Mirrors: db.transactions.filter(t => t.status === 'Failed').length
        $failedCount = Payment::where('status', 'Failed')->count();

        return response()->json([
            'revenueThisMonth' => (float) $revenueThisMonth,
            'pendingCount'     => $pendingCount,
            'overdueCount'     => $overdueCount,
            'failedCount'      => $failedCount,
        ]);
    }
}
