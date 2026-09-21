<?php

namespace App\Modules\Candidate\Interfaces\Http\Requests;

use App\Modules\Candidate\Interfaces\Authorization\CandidatePolicy;
use App\Modules\Identity\Application\Contracts\CanonicalEmailNormalizer;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class UpdateCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get(TenantContext::class);

        return $tenant instanceof TenantContext
            && Gate::allows(CandidatePolicy::UPDATE, $tenant);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'availability' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
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

    public function candidateId(): int
    {
        $candidateId = filter_var($this->route('candidate'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! is_int($candidateId)) {
            throw new LogicException('The candidate route identifier is invalid.');
        }

        return $candidateId;
    }

    /**
     * @return array<string, string|null>
     */
    public function candidateChanges(): array
    {
        $changes = [];

        foreach (self::writableFields() as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->validated($field);
            $changes[$field] = $value === null ? null : (string) $value;
        }

        return $changes;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (self::writableFields() as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);

            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }

        $email = $values['email'] ?? $this->input('email');

        if ($this->exists('email') && is_string($email)) {
            $normalizer = $this->container->make(CanonicalEmailNormalizer::class);
            $values['email'] = $normalizer->normalize($email);
        }

        $this->merge($values);
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
