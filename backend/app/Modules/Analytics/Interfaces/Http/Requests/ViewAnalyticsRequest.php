<?php

namespace App\Modules\Analytics\Interfaces\Http\Requests;

use App\Modules\Analytics\Application\Data\AnalyticsPeriod;
use App\Modules\Analytics\Interfaces\Authorization\AnalyticsPolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use LogicException;

final class ViewAnalyticsRequest extends FormRequest
{
    private const array ALLOWED_QUERY = ['from', 'to'];

    private const int MAXIMUM_DAYS = 365;

    public function authorize(): bool
    {
        return Gate::allows(AnalyticsPolicy::VIEW, $this->tenantContext());
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknown = array_diff(array_keys($this->query()), self::ALLOWED_QUERY);
            if ($unknown !== []) {
                $validator->errors()->add('query', 'Unsupported query parameters: '.implode(', ', $unknown).'.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = $this->validated('from');
            $to = $this->validated('to');
            if (! is_string($from) || ! is_string($to)) {
                return;
            }

            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, 'UTC');
            if ($start === null || $end === null) {
                return;
            }
            if ($start->isAfter($end)) {
                $validator->errors()->add('to', 'The to date must be on or after the from date.');

                return;
            }
            if ($start->diffInDays($end) + 1 > self::MAXIMUM_DAYS) {
                $validator->errors()->add('to', 'The reporting period must not exceed 365 days.');
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

    public function period(): AnalyticsPeriod
    {
        $from = $this->validated('from');
        $to = $this->validated('to');
        if (is_string($from) && is_string($to)) {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, 'UTC');
            if ($start === null || $end === null) {
                throw new LogicException('The validated analytics period could not be parsed.');
            }

            return new AnalyticsPeriod(
                $start,
                $end,
            );
        }

        $today = CarbonImmutable::today('UTC');

        return new AnalyticsPeriod($today->subDays(89), $today);
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['from', 'to'] as $field) {
            $value = $this->query($field);
            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }
        $this->merge($values);
    }
}
