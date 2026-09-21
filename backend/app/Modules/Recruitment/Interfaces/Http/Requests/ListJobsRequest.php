<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Recruitment\Application\Data\JobListCriteria;
use App\Modules\Recruitment\Domain\JobStatus;
use App\Modules\Recruitment\Interfaces\Authorization\JobPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListJobsRequest extends JobRequest
{
    private const array ALLOWED = ['search', 'status', 'occupation', 'employment_type', 'sort', 'direction', 'page', 'per_page'];

    public function authorize(): bool
    {
        return Gate::allows(JobPolicy::VIEW, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::enum(JobStatus::class)],
            'occupation' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['opened_at', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknown = array_diff(array_keys($this->query()), self::ALLOWED);
            if ($unknown !== []) {
                $validator->errors()->add('query', 'Unsupported query parameters: '.implode(', ', $unknown).'.');
            }
        }];
    }

    public function criteria(): JobListCriteria
    {
        $status = $this->validated('status');

        return new JobListCriteria(
            search: $this->optionalString('search'),
            status: is_string($status) ? JobStatus::from($status) : null,
            occupation: $this->optionalString('occupation'),
            employmentType: $this->optionalString('employment_type'),
            sort: (string) ($this->validated('sort') ?? 'created_at'),
            direction: (string) ($this->validated('direction') ?? 'desc'),
            page: (int) ($this->validated('page') ?? 1),
            perPage: (int) ($this->validated('per_page') ?? 20),
        );
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['search', 'status', 'occupation', 'employment_type', 'sort', 'direction'] as $field) {
            $value = $this->query($field);
            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }
        $this->merge($values);
    }

    private function optionalString(string $field): ?string
    {
        $value = $this->validated($field);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
