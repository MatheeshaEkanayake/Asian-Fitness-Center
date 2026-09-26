<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for POST /api/setup/roles (create a new role).
 *
 * `permissions` entries are validated against App\Support\Permissions::ALL,
 * the same canonical key list the frontend's navigationTree.js mirrors.
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:50', 'unique:roles,name'],
            'description'   => ['nullable', 'string'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => [Rule::in(Permissions::ALL)],
            'is_admin'      => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Role name is required.',
            'name.unique'     => 'A role with this name already exists.',
            'permissions.*.in' => 'Unknown permission key.',
        ];
    }
}
