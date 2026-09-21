<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Recruitment\Application\Data\ApplicationListCriteria;
use App\Modules\Recruitment\Domain\ApplicationStatus;
use App\Modules\Recruitment\Interfaces\Authorization\ApplicationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListApplicationsRequest extends ApplicationRequest
{
    private const array ALLOWED = ['job_id', 'candidate_id', 'status', 'sort', 'direction', 'page', 'per_page'];

    public function authorize(): bool
    {
        return Gate::allows(ApplicationPolicy::VIEW, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'job_id' => ['nullable', 'integer', 'min:1'],
            'candidate_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'sort' => ['nullable', Rule::in(['applied_at', 'updated_at'])],
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

    public function criteria(): ApplicationListCriteria
    {
        $status = $this->validated('status');

        return new ApplicationListCriteria(
            jobId: $this->optionalInt('job_id'),
            candidateId: $this->optionalInt('candidate_id'),
            status: is_string($status) ? ApplicationStatus::from($status) : null,
            sort: (string) ($this->validated('sort') ?? 'applied_at'),
            direction: (string) ($this->validated('direction') ?? 'desc'),
            page: (int) ($this->validated('page') ?? 1),
            perPage: (int) ($this->validated('per_page') ?? 20),
        );
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['status', 'sort', 'direction'] as $field) {
            $value = $this->query($field);
            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }
        $this->merge($values);
    }

    private function optionalInt(string $field): ?int
    {
        $value = $this->validated($field);

        return is_int($value) ? $value : (is_string($value) ? (int) $value : null);
    }
}
