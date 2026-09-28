<?php

namespace App\Modules\Compliance\Interfaces\Http\Requests;

use App\Modules\Compliance\Application\Data\QualificationDefinitionData;
use App\Modules\Compliance\Interfaces\Authorization\CompliancePolicy;
use Illuminate\Support\Facades\Gate;

class StoreQualificationDefinitionRequest extends ComplianceRequest
{
    public function authorize(): bool
    {
        return Gate::allows(CompliancePolicy::MANAGE_CATALOGUE, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'id' => ['prohibited'], 'organisation_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['name', 'category', 'description'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }
        $this->merge($values);
    }

    public function definitionData(bool $defaultActive = true): QualificationDefinitionData
    {
        $category = $this->validated('category');
        $description = $this->validated('description');

        return new QualificationDefinitionData(
            (string) $this->validated('name'),
            $category === null ? null : (string) $category,
            $description === null ? null : (string) $description,
            (bool) $this->validated('is_active', $defaultActive),
        );
    }
}
