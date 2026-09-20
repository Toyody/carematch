<?php

namespace App\Modules\Organisation\Application\Actions;

use App\Modules\Identity\Application\Contracts\IdentityUserLookup;
use App\Modules\Organisation\Application\Contracts\OrganisationMembershipLister;
use App\Modules\Organisation\Application\Data\OrganisationMembershipRecord;
use App\Modules\Organisation\Application\Data\OrganisationMembershipSummary;
use App\Modules\Organisation\Application\Data\TenantContext;
use LogicException;

final readonly class ListOrganisationMemberships
{
    public function __construct(
        private OrganisationMembershipLister $memberships,
        private IdentityUserLookup $users,
    ) {}

    /**
     * @return list<OrganisationMembershipSummary>
     */
    public function handle(TenantContext $tenant): array
    {
        $memberships = $this->memberships->forOrganisation($tenant);
        $users = $this->users->findByIds(array_values(array_unique(array_map(
            static fn (OrganisationMembershipRecord $membership): int => $membership->userId,
            $memberships,
        ))));

        return array_map(
            static function (OrganisationMembershipRecord $membership) use ($users): OrganisationMembershipSummary {
                $user = $users[$membership->userId] ?? null;

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
            },
            $memberships,
        );
    }
}
