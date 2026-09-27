<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for PUT /api/setup/users/{id} (update an existing staff account).
 *
 * Password is intentionally not accepted here — use
 * POST /api/setup/users/{id}/reset-password (UserController::resetPassword)
 * for admin-initiated password changes, keeping that as a deliberate,
 * separate action rather than a silent field on the general update form.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email'     => ['sometimes', 'required', 'email', "unique:users,email,{$userId}"],
            // Door PIN, saved on their registration (members.member_id_number).
            'member_id_number' => ['sometimes', 'nullable', 'regex:/^\d{1,9}$/', Rule::unique('members', 'member_id_number')->ignore($this->route('user')?->member_id)],
            'role_id'   => ['sometimes', 'nullable', 'exists:roles,id'],
            'branch_id' => ['sometimes', 'nullable', 'exists:branches,id'],
            'status'    => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Full name is required.',
            'email.required'     => 'Email is required.',
            'email.email'        => 'Enter a valid email address.',
            'email.unique'       => 'A user with this email already exists.',
            'member_id_number.regex'  => 'Member ID number must be 1–9 digits (it is the door PIN).',
            'member_id_number.unique' => 'That Member ID number is already used by someone else.',
            'role_id.exists'     => 'Selected role does not exist.',
            'branch_id.exists'   => 'Selected branch does not exist.',
        ];
    }
}
