<?php

namespace App\Modules\Organisation\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AcceptOrganisationInvitationRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'organisation_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'role' => ['prohibited'],
            'membership_id' => ['prohibited'],
        ];
    }
}
