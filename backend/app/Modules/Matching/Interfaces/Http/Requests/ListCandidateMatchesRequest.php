<?php

namespace App\Modules\Matching\Interfaces\Http\Requests;

use App\Modules\Matching\Application\Data\MatchCriteria;
use App\Modules\Matching\Interfaces\Authorization\MatchingPolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use LogicException;

final class ListCandidateMatchesRequest extends FormRequest
{
    private const array ALLOWED = ['page', 'per_page', 'max_distance_km'];

    public function authorize(): bool
    {
        return Gate::allows(MatchingPolicy::VIEW, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_distance_km' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
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

    public function tenantContext(): TenantContext
    {
        $tenant = $this->attributes->get(TenantContext::class);
        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        return $tenant;
    }

    public function jobId(): int
    {
        $jobId = filter_var($this->route('job'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($jobId)) {
            throw new LogicException('The job route identifier is invalid.');
        }

        return $jobId;
    }

    public function criteria(): MatchCriteria
    {
        $radius = $this->validated('max_distance_km');

        return new MatchCriteria(
            page: (int) ($this->validated('page') ?? 1),
            perPage: (int) ($this->validated('per_page') ?? 20),
            maxDistanceKm: $radius === null ? null : (float) $radius,
        );
    }
}
