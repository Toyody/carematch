<?php

namespace App\Modules\Organisation\Interfaces\Http\Requests;

use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class RevokeOrganisationInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get(TenantContext::class);

        return $tenant instanceof TenantContext
            && Gate::allows('manageInvitations', $tenant);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function tenantContext(): TenantContext
    {
        $tenant = $this->attributes->get(TenantContext::class);

        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        return $tenant;
    }
}
