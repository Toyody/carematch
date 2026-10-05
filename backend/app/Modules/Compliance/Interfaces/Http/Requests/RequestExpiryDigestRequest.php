<?php

namespace App\Modules\Compliance\Interfaces\Http\Requests;

use App\Modules\Compliance\Interfaces\Authorization\CompliancePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

final class RequestExpiryDigestRequest extends ComplianceRequest
{
    public function authorize(): bool
    {
        return Gate::allows(CompliancePolicy::REQUEST_EXPIRY_DIGEST, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:128', 'regex:/^[\x21-\x7E]+$/'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $bodyKeys = $this->attributes->get('expiry_digest_body_keys', []);
            if (is_array($bodyKeys) && $bodyKeys !== []) {
                $validator->errors()->add('body', 'This operation does not accept request body fields.');
            }
        }];
    }

    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotency_key');
    }

    protected function prepareForValidation(): void
    {
        $this->attributes->set('expiry_digest_body_keys', array_keys($this->request->all()));
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }
}
