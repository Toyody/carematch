<?php

namespace App\Modules\Ai\Interfaces\Http\Requests;

use App\Modules\Ai\Interfaces\Authorization\AiPolicy;
use App\Modules\Identity\Application\Contracts\CanonicalEmailNormalizer;
use Illuminate\Support\Facades\Gate;

final class ApplyCvExtractionRequest extends AiTenantRequest
{
    public function authorize(): bool
    {
        return Gate::allows(AiPolicy::USE, $this->tenantContext());
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array:'.implode(',', self::fields()), 'min:1'],
            'fields.first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'fields.last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'fields.email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'fields.phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'fields.occupation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields.location' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $fields = $this->input('fields');
        if (! is_array($fields)) {
            return;
        }
        foreach (self::fields() as $field) {
            if (isset($fields[$field]) && is_string($fields[$field])) {
                $fields[$field] = trim($fields[$field]);
            }
        }
        if (isset($fields['email']) && is_string($fields['email'])) {
            $fields['email'] = $this->container->make(CanonicalEmailNormalizer::class)->normalize($fields['email']);
        }
        $this->merge(['fields' => $fields]);
    }

    /** @return array<string, string|null> */
    public function changes(): array
    {
        /** @var array<string, string|null> $fields */
        $fields = $this->validated('fields');

        return $fields;
    }

    /** @return list<string> */
    private static function fields(): array
    {
        return ['first_name', 'last_name', 'email', 'phone', 'occupation', 'location'];
    }
}
