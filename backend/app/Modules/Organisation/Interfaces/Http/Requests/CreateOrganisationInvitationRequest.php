<?php

namespace App\Modules\Organisation\Interfaces\Http\Requests;

use App\Modules\Identity\Application\Contracts\CanonicalEmailNormalizer;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use LogicException;

final class CreateOrganisationInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get(TenantContext::class);

        return $tenant instanceof TenantContext
            && Gate::allows('manageInvitations', $tenant);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::enum(OrganisationRole::class)],
            'organisation_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'invited_by_user_id' => ['prohibited'],
            'membership_id' => ['prohibited'],
            'token_hash' => ['prohibited'],
            'accepted_at' => ['prohibited'],
            'revoked_at' => ['prohibited'],
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

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (! is_string($email)) {
            return;
        }

        $normalizer = $this->container->make(CanonicalEmailNormalizer::class);

        $this->merge(['email' => $normalizer->normalize($email)]);
    }
}
