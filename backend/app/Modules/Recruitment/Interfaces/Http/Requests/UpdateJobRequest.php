<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Recruitment\Interfaces\Authorization\JobPolicy;
use DateTimeImmutable;
use Illuminate\Support\Facades\Gate;

final class UpdateJobRequest extends JobRequest
{
    public function authorize(): bool
    {
        return Gate::allows(JobPolicy::UPDATE, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employment_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'opened_at' => ['sometimes', 'nullable', 'date'],
            'closes_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['prohibited'],
            'id' => ['prohibited'],
            'organisation_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'role' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    /** @return array<string, string|DateTimeImmutable|null> */
    public function jobChanges(): array
    {
        $changes = [];
        foreach (['title', 'occupation', 'location', 'employment_type', 'description'] as $field) {
            if ($this->exists($field)) {
                $value = $this->validated($field);
                $changes[$field] = $value === null ? null : (string) $value;
            }
        }
        foreach (['opened_at', 'closes_at'] as $field) {
            if ($this->exists($field)) {
                $value = $this->validated($field);
                $changes[$field] = $value === null ? null : new DateTimeImmutable((string) $value);
            }
        }

        return $changes;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['title', 'occupation', 'location', 'employment_type', 'description', 'opened_at', 'closes_at'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $values[$field] = trim((string) $this->input($field));
            }
        }
        $this->merge($values);
    }
}
