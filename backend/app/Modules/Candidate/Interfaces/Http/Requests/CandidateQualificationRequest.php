<?php

namespace App\Modules\Candidate\Interfaces\Http\Requests;

use App\Modules\Candidate\Application\Data\CandidateQualificationData;
use App\Modules\Candidate\Interfaces\Authorization\CandidatePolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class CandidateQualificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = in_array($this->method(), ['GET', 'HEAD'], true)
            ? CandidatePolicy::VIEW_QUALIFICATIONS
            : CandidatePolicy::MANAGE_QUALIFICATIONS;

        return Gate::allows($ability, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        if (in_array($this->method(), ['GET', 'HEAD', 'DELETE'], true)) {
            return [];
        }

        return [
            'qualification_definition_id' => ['required', 'integer', 'min:1'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'credential_number' => ['nullable', 'string', 'max:255'],
            'issued_on' => ['nullable', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issued_on'],
            'organisation_id' => ['prohibited'], 'candidate_id' => ['prohibited'], 'id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['issuer', 'credential_number'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }
        $this->merge($values);
    }

    public function tenantContext(): TenantContext
    {
        $tenant = $this->attributes->get(TenantContext::class);
        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        return $tenant;
    }

    public function routeId(string $name): int
    {
        $value = $this->route($name);
        if (! is_string($value) || ! ctype_digit($value) || (int) $value < 1) {
            throw new LogicException('Invalid route identifier.');
        }

        return (int) $value;
    }

    public function qualificationData(): CandidateQualificationData
    {
        $issuer = $this->validated('issuer');
        $number = $this->validated('credential_number');
        $issuedOn = $this->validated('issued_on');
        $expiresOn = $this->validated('expires_on');

        return new CandidateQualificationData(
            (int) $this->validated('qualification_definition_id'),
            $issuer === null ? null : (string) $issuer,
            $number === null ? null : (string) $number,
            $issuedOn === null ? null : new DateTimeImmutable((string) $issuedOn),
            $expiresOn === null ? null : new DateTimeImmutable((string) $expiresOn),
        );
    }
}
