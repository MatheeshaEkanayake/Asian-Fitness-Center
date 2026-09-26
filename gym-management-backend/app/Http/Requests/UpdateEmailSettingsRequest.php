<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for PUT /api/setup/email.
 */
class UpdateEmailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'host'         => ['required', 'string', 'max:255'],
            'port'         => ['required', 'integer'],
            'encryption'   => ['nullable', 'in:tls,ssl'],
            'username'     => ['nullable', 'string', 'max:255'],
            'password'     => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email'],
            'from_name'    => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'host.required'         => 'SMTP host is required.',
            'port.required'         => 'SMTP port is required.',
            'from_address.required' => 'From address is required.',
            'from_address.email'    => 'Enter a valid from address.',
            'from_name.required'    => 'From name is required.',
        ];
    }
}
