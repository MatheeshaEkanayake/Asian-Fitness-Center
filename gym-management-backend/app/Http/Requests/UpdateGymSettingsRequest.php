<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for PUT /api/setup/gym.
 */
class UpdateGymSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'tagline'     => ['nullable', 'string', 'max:255'],
            'email'       => ['nullable', 'email', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:50'],
            'address'     => ['nullable', 'string', 'max:500'],
            'website'     => ['nullable', 'string', 'max:255'],
            'logo'        => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Gym name is required.',
            'email.email'   => 'Enter a valid email address.',
            'logo.image'    => 'Logo must be an image file.',
            'logo.mimes'    => 'Logo must be a PNG, JPG, GIF or WebP file.',
            'logo.max'      => 'Logo must be 2MB or smaller.',
        ];
    }
}
