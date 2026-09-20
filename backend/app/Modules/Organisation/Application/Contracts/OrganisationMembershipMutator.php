<?php

namespace App\Modules\Organisation\Application\Contracts;

use App\Modules\Organisation\Application\Data\OrganisationMembershipRecord;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;

interface OrganisationMembershipMutator
{
    public function changeRole(
        TenantContext $tenant,
        int $membershipId,
        OrganisationRole $role,
    ): OrganisationMembershipRecord;

    public function deactivate(
        TenantContext $tenant,
        int $membershipId,
        DateTimeImmutable $deactivatedAt,
    ): void;
}
