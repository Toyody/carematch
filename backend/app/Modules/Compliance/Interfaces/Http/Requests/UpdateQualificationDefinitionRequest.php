<?php

namespace App\Modules\Compliance\Interfaces\Http\Requests;

final class UpdateQualificationDefinitionRequest extends StoreQualificationDefinitionRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'is_active' => ['required', 'boolean'],
        ];
    }
}
