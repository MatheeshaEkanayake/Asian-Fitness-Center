<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for POST /api/payments (record a new payment).
 *
 * NOTE: renamed from StoreTransactionRequest to match the
 * Transaction → Payment model/table rename.
 *
 * Mirrors the validate() function in:
 *   Frontend: src/pages/payments/TransactionFormPage.jsx
 *
 * Frontend rules ported:
 *   - memberId: required → member_id must exist in members table
 *   - amount:   required, > 0
 *   - method:   required, must be a valid enum value
 *
 * Additional server-side rules (not in frontend):
 *   - member_id must reference an existing member record
 */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Required (mirrors frontend validate() in TransactionFormPage.jsx)
            'member_id' => ['required', 'exists:members,id'],
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'method'    => ['required', 'in:Cash,Card,Bank Transfer,Online'],

            // Optional with defaults applied in PaymentController::store()
            'type'     => ['nullable', 'in:OneTime,Recurring'],
            'date'     => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status'   => ['nullable', 'in:Paid,Pending,Failed'],
            'notes'    => ['nullable', 'string'],

            // The plan this payment is for (defaults to the member's plan on
            // the payment form). Any existing plan is accepted, so a payment
            // can still be recorded for a member on a since-retired plan.
            'payment_plan_id' => ['nullable', 'exists:payment_plans,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'Select a member.',
            'member_id.exists'   => 'The selected member does not exist.',
            'amount.required'    => 'Enter an amount greater than 0.',
            'amount.min'         => 'Enter an amount greater than 0.',
            'method.required'    => 'Select a payment method.',
            'payment_plan_id.exists' => 'The selected payment plan does not exist.',
            'method.in'          => 'Payment method must be Cash, Card, Bank Transfer, or Online.',
        ];
    }
}
