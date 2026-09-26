<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for POST /api/setup/users — grant a registered member a staff
 * role. Name, email and login come from the registration, so they aren't
 * accepted here.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => [
                'required',
                'integer',
                'unique:users,member_id',
                // Must be a registration (signed up with a username).
                Rule::exists('members', 'id')->whereNotNull('username'),
            ],
            'role_id'   => ['required', 'exists:roles,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'status'    => ['nullable', 'in:Active,Inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'Select a registered person.',
            'member_id.exists'   => 'That person has not registered an account.',
            'member_id.unique'   => 'That person already has a staff role.',
            'role_id.required'   => 'Select a role.',
            'role_id.exists'     => 'Selected role does not exist.',
            'branch_id.exists'   => 'Selected branch does not exist.',
        ];
    }
}
