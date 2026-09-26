<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for PUT /api/setup/roles/{id} (update an existing role).
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'name'          => ['sometimes', 'required', 'string', 'max:50', "unique:roles,name,{$roleId}"],
            'description'   => ['sometimes', 'nullable', 'string'],
            'permissions'   => ['sometimes', 'nullable', 'array'],
            'permissions.*' => [Rule::in(Permissions::ALL)],
            'is_admin'      => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Role name is required.',
            'name.unique'      => 'A role with this name already exists.',
            'permissions.*.in' => 'Unknown permission key.',
        ];
    }
}
