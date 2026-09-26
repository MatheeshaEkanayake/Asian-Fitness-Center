<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentPlanRequest;
use App\Models\PaymentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PaymentPlanController — Setup > Payment Plans.
 *
 * index() is readable by anyone who works with members or payments (the
 * member form and payment form both pick a plan); store()/update()/updateStatus()
 * are gated behind setup.manage — see routes/api.php.
 *
 * Plans can be edited (price/length changes) and retired/reactivated, but
 * not deleted, since members and payments reference them. Payments keep
 * their own recorded amount, so editing a plan's price doesn't change past
 * payment amounts.
 *
 * Frontend: src/services/paymentPlanService.js, src/context/PaymentPlansContext.jsx
 */
class PaymentPlanController extends Controller
{
    /**
     * GET /api/payment-plans
     *
     * All plans (active and retired), Daily first, then Monthly by length.
     */
    public function index(): JsonResponse
    {
        $plans = PaymentPlan::orderByRaw("type = 'Monthly'")
            ->orderBy('months')
            ->orderBy('amount')
            ->get();

        return response()->json($plans);
    }

    /**
     * GET /api/public/payment-plans
     *
     * Current (active) plans only, for the public signup page — no auth.
     */
    public function publicIndex(): JsonResponse
    {
        $plans = PaymentPlan::where('is_active', true)
            ->orderByRaw("type = 'Monthly'")
            ->orderBy('months')
            ->orderBy('amount')
            ->get(['id', 'type', 'months', 'amount']);

        return response()->json($plans);
    }

    /**
     * POST /api/setup/payment-plans
     */
    public function store(StorePaymentPlanRequest $request): JsonResponse
    {
        $data = $request->validated();

        $plan = PaymentPlan::create([
            'type'      => $data['type'],
            'months'    => $data['type'] === 'Monthly' ? $data['months'] : null,
            'amount'    => $data['amount'],
            'is_active' => true,
        ]);

        return response()->json($plan, 201);
    }

    /**
     * PUT /api/setup/payment-plans/{id}
     *
     * Change a plan's type, length or price. Same rules as store().
     */
    public function update(StorePaymentPlanRequest $request, PaymentPlan $paymentPlan): JsonResponse
    {
        $data = $request->validated();

        $paymentPlan->update([
            'type'   => $data['type'],
            'months' => $data['type'] === 'Monthly' ? $data['months'] : null,
            'amount' => $data['amount'],
        ]);

        return response()->json($paymentPlan->fresh());
    }

    /**
     * PATCH /api/setup/payment-plans/{id}/status
     *
     * Retire (is_active = false) or reactivate a plan. A retired plan stays
     * on members already signed up to it, but can't be chosen for new ones.
     */
    public function updateStatus(Request $request, PaymentPlan $paymentPlan): JsonResponse
    {
        $request->validate(['is_active' => 'required|boolean']);

        $paymentPlan->update(['is_active' => $request->boolean('is_active')]);

        return response()->json($paymentPlan->fresh());
    }
}
