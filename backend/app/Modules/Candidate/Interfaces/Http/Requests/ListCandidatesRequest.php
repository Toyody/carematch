<?php

namespace App\Modules\Candidate\Interfaces\Http\Requests;

use App\Modules\Candidate\Application\Data\CandidateListCriteria;
use App\Modules\Candidate\Interfaces\Authorization\CandidatePolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

final class ListCandidatesRequest extends FormRequest
{
    private const array ALLOWED_QUERY_PARAMETERS = [
        'search',
        'occupation',
        'sort',
        'direction',
        'page',
        'per_page',
    ];

    public function authorize(): bool
    {
        $tenant = $this->attributes->get(TenantContext::class);

        return $tenant instanceof TenantContext
            && Gate::allows(CandidatePolicy::VIEW, $tenant);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['name', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknown = array_diff(
                array_keys($this->query()),
                self::ALLOWED_QUERY_PARAMETERS,
            );

            if ($unknown !== []) {
                $validator->errors()->add(
                    'query',
                    'Unsupported query parameters: '.implode(', ', $unknown).'.',
                );
            }
        }];
    }

    public function tenantContext(): TenantContext
    {
        $tenant = $this->attributes->get(TenantContext::class);

        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        return $tenant;
    }

    public function criteria(): CandidateListCriteria
    {
        return new CandidateListCriteria(
            search: $this->optionalString('search'),
            occupation: $this->optionalString('occupation'),
            sort: (string) ($this->validated('sort') ?? 'created_at'),
            direction: (string) ($this->validated('direction') ?? 'desc'),
            page: (int) ($this->validated('page') ?? 1),
            perPage: (int) ($this->validated('per_page') ?? 20),
        );
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['search', 'occupation'] as $field) {
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
