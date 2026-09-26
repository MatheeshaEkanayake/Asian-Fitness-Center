<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for PUT /api/setup/branches/{id} (update an existing branch).
 */
class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = $this->route('branch')?->id;

        return [
            'name'    => ['sometimes', 'required', 'string', 'max:255', "unique:branches,name,{$branchId}"],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone'   => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Branch name is required.',
            'name.unique'   => 'A branch with this name already exists.',
        ];
    }
}
