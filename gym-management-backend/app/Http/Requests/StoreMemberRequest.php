<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for POST /api/members (create a new member).
 *
 * Mirrors the validate() function in:
 *   Frontend: src/pages/members/MemberFormPage.jsx
 *
 * Required fields match the star-marked list the member form was built
 * from: full name, DOB, phone, WhatsApp number, gender, weight, height,
 * username and password. Email is intentionally optional here (not every
 * member has one) — phone/WhatsApp are the required contact channels.
 *
 * Password confirmation uses Laravel's built-in `confirmed` rule, which
 * expects a sibling `password_confirmation` field (sent as
 * `passwordConfirmation` from the frontend — apiClient's camelCase/snake_case
 * conversion maps it to the name this rule expects automatically).
 */
class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id_number' => ['nullable', 'string', 'max:100'],
            'full_name'        => ['required', 'string', 'max:255'],
            'nic'              => ['nullable', 'string', 'max:50'],
            'email'            => ['nullable', 'email', 'unique:members,email'],
            'phone'            => ['required', 'string', 'max:50'],
            'whatsapp_number'  => ['required', 'string', 'max:50'],
            'dob'              => ['required', 'date'],
            'gender'           => ['required', 'in:Female,Male,Other'],
            'address'          => ['nullable', 'string', 'max:500'],
            'weight_kg'        => ['required', 'numeric', 'min:0', 'max:999'],
            'height_cm'        => ['required', 'numeric', 'min:0', 'max:999'],
            'occupation'       => ['nullable', 'string', 'max:255'],

            'emergency_contact_name'  => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],

            'username' => ['required', 'string', 'max:100', 'unique:members,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            // New members must be put on a plan the gym currently offers.
            'payment_plan_id' => ['required', Rule::exists('payment_plans', 'id')->where('is_active', true)],

            'join_date' => ['nullable', 'date'],
            'status'    => ['nullable', 'in:Active,Inactive,Suspended'],
            'notes'     => ['nullable', 'string'],
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
            'username.required'       => 'Preferred username is required.',
            'username.unique'         => 'That username is already taken.',
            'password.required'       => 'Password is required.',
            'password.min'            => 'Password must be at least 8 characters.',
            'password.confirmed'      => 'Passwords do not match.',
        ];
    }
}
