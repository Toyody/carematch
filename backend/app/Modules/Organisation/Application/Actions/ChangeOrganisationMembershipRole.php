<?php

namespace App\Modules\Organisation\Application\Actions;

use App\Modules\Identity\Application\Contracts\IdentityUserLookup;
use App\Modules\Organisation\Application\Contracts\OrganisationMembershipMutator;
use App\Modules\Organisation\Application\Data\OrganisationMembershipSummary;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use LogicException;

final readonly class ChangeOrganisationMembershipRole
{
    public function __construct(
        private OrganisationMembershipMutator $memberships,
        private IdentityUserLookup $users,
    ) {}

    public function handle(
        TenantContext $tenant,
        int $membershipId,
        OrganisationRole $role,
    ): OrganisationMembershipSummary {
        $membership = $this->memberships->changeRole($tenant, $membershipId, $role);
        $user = $this->users->findById($membership->userId);

        if ($user === null) {
            throw new LogicException('A membership references a missing identity user.');
        }

        return new OrganisationMembershipSummary(
            id: $membership->id,
            userId: $user->id,
            userName: $user->name,
            userEmail: $user->email,
            role: $membership->role,
            deactivatedAt: $membership->deactivatedAt,
            createdAt: $membership->createdAt,
            updatedAt: $membership->updatedAt,
        );
    }
}
