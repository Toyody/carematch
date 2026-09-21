<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Recruitment\Application\Data\JobData;
use App\Modules\Recruitment\Interfaces\Authorization\JobPolicy;
use DateTimeImmutable;
use Illuminate\Support\Facades\Gate;

final class CreateJobRequest extends JobRequest
{
    public function authorize(): bool
    {
        return Gate::allows(JobPolicy::CREATE, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
            'opened_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opened_at'],
            'status' => ['prohibited'],
            'id' => ['prohibited'],
            'organisation_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'role' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function jobData(): JobData
    {
        return new JobData(
            title: (string) $this->validated('title'),
            occupation: $this->nullableString('occupation'),
            location: $this->nullableString('location'),
            employmentType: $this->nullableString('employment_type'),
            description: $this->nullableString('description'),
            openedAt: $this->nullableDate('opened_at'),
            closesAt: $this->nullableDate('closes_at'),
        );
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['title', 'occupation', 'location', 'employment_type', 'description', 'opened_at', 'closes_at'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }
        $this->merge($values);
    }

    private function nullableString(string $field): ?string
    {
        $value = $this->validated($field);

        return $value === null ? null : (string) $value;
    }

    private function nullableDate(string $field): ?DateTimeImmutable
    {
        $value = $this->validated($field);

        return $value === null ? null : new DateTimeImmutable((string) $value);
    }
}
