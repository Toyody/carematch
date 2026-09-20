<?php

namespace App\Modules\Organisation\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateOrganisationRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['prohibited'],
            'organisation_id' => ['prohibited'],
            'role' => ['prohibited'],
        ];
    }
}
