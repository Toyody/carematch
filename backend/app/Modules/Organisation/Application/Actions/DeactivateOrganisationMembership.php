<?php

namespace App\Modules\Organisation\Application\Actions;

use App\Modules\Organisation\Application\Contracts\OrganisationMembershipMutator;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;
use DateTimeZone;

final readonly class DeactivateOrganisationMembership
{
    public function __construct(
        private OrganisationMembershipMutator $memberships,
    ) {}

    public function handle(TenantContext $tenant, int $membershipId): void
    {
        $this->memberships->deactivate(
            tenant: $tenant,
            membershipId: $membershipId,
            deactivatedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }
}
