<?php

namespace App\Modules\Candidate\Interfaces\Http\Requests;

use App\Modules\Candidate\Application\Data\CandidateData;
use App\Modules\Candidate\Interfaces\Authorization\CandidatePolicy;
use App\Modules\Identity\Application\Contracts\CanonicalEmailNormalizer;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class CreateCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get(TenantContext::class);

        return $tenant instanceof TenantContext
            && Gate::allows(CandidatePolicy::CREATE, $tenant);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'availability' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'id' => ['prohibited'],
            'organisation_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function tenantContext(): TenantContext
    {
        $tenant = $this->attributes->get(TenantContext::class);

        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        return $tenant;
    }

    public function candidateData(): CandidateData
    {
        return new CandidateData(
            firstName: (string) $this->validated('first_name'),
            lastName: (string) $this->validated('last_name'),
            email: $this->nullableValidatedString('email'),
            phone: $this->nullableValidatedString('phone'),
            occupation: $this->nullableValidatedString('occupation'),
            location: $this->nullableValidatedString('location'),
            availability: $this->nullableValidatedString('availability'),
            notes: $this->nullableValidatedString('notes'),
        );
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (self::writableFields() as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }

        $email = $values['email'] ?? $this->input('email');

        if (is_string($email)) {
            $normalizer = $this->container->make(CanonicalEmailNormalizer::class);
            $values['email'] = $normalizer->normalize($email);
        }

        $this->merge($values);
    }

    private function nullableValidatedString(string $field): ?string
    {
        $value = $this->validated($field);

        return $value === null ? null : (string) $value;
    }

    /**
     * @return list<string>
     */
    private static function writableFields(): array
    {
        return [
            'first_name',
            'last_name',
            'email',
            'phone',
            'occupation',
            'location',
            'availability',
            'notes',
        ];
    }
}
