<?php

namespace App\Modules\Audit\Interfaces\Http\Requests;

use App\Modules\Audit\Application\Data\AuditEventListCriteria;
use App\Modules\Audit\Interfaces\Authorization\AuditPolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use LogicException;

final class ListAuditEventsRequest extends FormRequest
{
    private const array ALLOWED = ['event_type', 'subject_type', 'subject_id', 'actor_user_id', 'occurred_from', 'occurred_to', 'page', 'per_page'];

    public function authorize(): bool
    {
        $tenant = $this->attributes->get(TenantContext::class);

        return $tenant instanceof TenantContext && Gate::allows(AuditPolicy::VIEW, $tenant);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'event_type' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/'],
            'subject_type' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'actor_user_id' => ['nullable', 'integer', 'min:1'],
            'occurred_from' => ['nullable', 'date_format:Y-m-d'],
            'occurred_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:occurred_from'],
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

    public function tenantContext(): TenantContext
    {
        $tenant = $this->attributes->get(TenantContext::class);
        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        return $tenant;
    }

    public function criteria(): AuditEventListCriteria
    {
        $from = $this->validated('occurred_from');
        $to = $this->validated('occurred_to');

        return new AuditEventListCriteria(
            eventType: $this->stringOrNull('event_type'),
            subjectType: $this->stringOrNull('subject_type'),
            subjectId: $this->integerOrNull('subject_id'),
            actorUserId: $this->integerOrNull('actor_user_id'),
            occurredFrom: is_string($from) ? new DateTimeImmutable($from.' 00:00:00 UTC') : null,
            occurredTo: is_string($to) ? new DateTimeImmutable($to.' 23:59:59.999999 UTC') : null,
            page: (int) ($this->validated('page') ?? 1),
            perPage: (int) ($this->validated('per_page') ?? 20),
        );
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integerOrNull(string $key): ?int
    {
        $value = $this->validated($key);

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
