<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for PUT /api/members/{id} (update an existing member).
 *
 * Mirrors the validate() function in:
 *   Frontend: src/pages/members/MemberFormPage.jsx (edit mode)
 *
 * All fields are optional (sometimes keyword) — only the fields
 * that are sent will be validated and updated. The email/username
 * uniqueness rules ignore the current member's own record.
 *
 * Password is deliberately NOT accepted here, same reasoning as
 * UpdateUserRequest: changing a password is a deliberate, separate action,
 * not a silent field on the general edit form (there is no
 * reset-password endpoint for members yet — add one if that's needed).
 */
class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Ignore the current member's own email/username when checking uniqueness
        $memberId = $this->route('member')?->id;
        $currentPlanId = $this->route('member')?->payment_plan_id;

        return [
            'member_id_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'full_name'        => ['sometimes', 'required', 'string', 'max:255'],
            'nic'              => ['sometimes', 'nullable', 'string', 'max:50'],
            'email'            => ['sometimes', 'nullable', 'email', "unique:members,email,{$memberId}"],
            'phone'            => ['sometimes', 'required', 'string', 'max:50'],
            'whatsapp_number'  => ['sometimes', 'required', 'string', 'max:50'],
            'dob'              => ['sometimes', 'required', 'date'],
            'gender'           => ['sometimes', 'required', 'in:Female,Male,Other'],
            'address'          => ['sometimes', 'nullable', 'string', 'max:500'],
            'weight_kg'        => ['sometimes', 'required', 'numeric', 'min:0', 'max:999'],
            'height_cm'        => ['sometimes', 'required', 'numeric', 'min:0', 'max:999'],
            'occupation'       => ['sometimes', 'nullable', 'string', 'max:255'],

            'emergency_contact_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],

            'username' => ['sometimes', 'required', 'string', 'max:100', "unique:members,username,{$memberId}"],

            // Must be an active plan, except the member may keep the plan they're
            // already on even if it has since been retired.
            'payment_plan_id' => [
                'sometimes',
                'required',
                Rule::exists('payment_plans', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->orWhere('id', $currentPlanId)
                ),
            ],

            'join_date' => ['sometimes', 'nullable', 'date'],
            'status'    => ['sometimes', 'nullable', 'in:Active,Inactive,Suspended'],
            'notes'     => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'       => 'Full name is required.',
            'email.email'              => 'Enter a valid email address.',
            'email.unique'             => 'A member with this email already exists.',
            'phone.required'           => 'Phone number is required.',
            'whatsapp_number.required' => 'WhatsApp number is required.',
            'dob.required'             => 'Date of birth is required.',
            'gender.required'          => 'Gender is required.',
            'weight_kg.required'       => 'Weight is required.',
            'height_cm.required'       => 'Height is required.',
            'payment_plan_id.required' => 'Select a payment plan.',
            'payment_plan_id.exists'   => 'That payment plan is no longer available.',
            'username.required' => 'Preferred username is required.',
            'username.unique'   => 'That username is already taken.',
        ];
    }
}
