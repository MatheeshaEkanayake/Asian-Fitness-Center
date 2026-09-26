<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'   => ['required', 'in:Daily,Monthly'],
            // Months only applies to Monthly plans; ignored (stored as null)
            // for Daily — see PaymentPlanController::store().
            'months' => ['required_if:type,Monthly', 'nullable', 'integer', 'min:1', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'      => 'Choose daily or monthly.',
            'months.required_if' => 'Enter how many months the plan covers.',
            'months.min'         => 'A monthly plan must cover at least 1 month.',
            'amount.required'    => 'Enter an amount greater than 0.',
            'amount.min'         => 'Enter an amount greater than 0.',
        ];
    }
}
