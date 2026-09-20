<?php

namespace App\Modules\Organisation\Interfaces\Http\Requests;

use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class DeactivateOrganisationMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get(TenantContext::class);

        return $tenant instanceof TenantContext
            && Gate::allows('manageMemberships', $tenant);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'organisation_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'membership_id' => ['prohibited'],
            'role' => ['prohibited'],
            'deactivated_at' => ['prohibited'],
            'actor_user_id' => ['prohibited'],
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

    public function membershipId(): int
    {
        $membershipId = filter_var($this->route('membership'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! is_int($membershipId)) {
            throw new LogicException('The membership route identifier is invalid.');
        }

        return $membershipId;
    }
}
