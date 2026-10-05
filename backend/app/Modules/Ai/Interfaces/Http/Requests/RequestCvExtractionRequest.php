<?php

namespace App\Modules\Ai\Interfaces\Http\Requests;

use App\Modules\Ai\Interfaces\Authorization\AiPolicy;
use Illuminate\Support\Facades\Gate;

final class RequestCvExtractionRequest extends AiTenantRequest
{
    public function authorize(): bool
    {
        return Gate::allows(AiPolicy::USE, $this->tenantContext());
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'string', 'max:128', 'regex:/^[\x20-\x7E]+$/']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotency_key');
    }
}
