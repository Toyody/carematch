<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Organisation\Application\Contracts\ActiveTenantMembershipResolver;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;

final class EloquentActiveTenantMembershipResolver implements ActiveTenantMembershipResolver
{
    public function resolve(int $userId, int $organisationId): ?TenantContext
    {
        $membership = OrganisationMembership::query()
            ->where('organisation_id', $organisationId)
            ->where('user_id', $userId)
            ->whereNull('deactivated_at')
            ->first(['id', 'organisation_id', 'user_id', 'role']);

        if ($membership === null) {
            return null;
        }

        return new TenantContext(
            organisationId: (int) $membership->getAttribute('organisation_id'),
            userId: (int) $membership->getAttribute('user_id'),
            membershipId: (int) $membership->getKey(),
            role: OrganisationRole::from((string) $membership->getAttribute('role')),
        );
    }
}
