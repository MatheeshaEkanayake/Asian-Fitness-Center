<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public self-signup (POST /api/auth/signup).
 *
 * Everyone registers as a Guest with just their basic details. Staff fill in
 * the rest (plan, door PIN) when they make them a member — see
 * GuestController::promote. Staff accounts also start as a signup, then get
 * a role in Setup > Users.
 *
 * Mirrors validate() in src/pages/auth/SignupPage.jsx.
 */
class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'       => ['required', 'string', 'max:255'],
            'phone'           => ['required', 'string', 'max:50'],
            'whatsapp_number' => ['required', 'string', 'max:50'],
            'dob'             => ['required', 'date', 'before:today'],
            'username'        => ['required', 'string', 'max:100', 'unique:members,username'],
            'password'        => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'       => 'Full name is required.',
            'phone.required'           => 'Phone number is required.',
            'whatsapp_number.required' => 'WhatsApp number is required.',
            'dob.required'             => 'Date of birth is required.',
            'dob.before'               => 'Date of birth must be in the past.',
            'username.required'        => 'Username is required.',
            'username.unique'          => 'That username is already taken.',
            'password.required'        => 'Password is required.',
            'password.min'             => 'Password must be at least 8 characters.',
            'password.confirmed'       => 'Passwords do not match.',
        ];
    }
}
