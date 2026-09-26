<?php

namespace App\Http\Requests;

/**
 * Public member self-signup (POST /api/auth/signup).
 *
 * Same rules as StoreMemberRequest (the staff "Add member" form), minus the
 * fields only staff should set: member ID number, join date, status, notes.
 * AuthController::signup() fills join date/status itself.
 */
class SignupRequest extends StoreMemberRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['member_id_number'], $rules['join_date'], $rules['status'], $rules['notes']);

        return $rules;
    }
}
