<?php

namespace App\Modules\Organisation\Application\Actions;

use App\Modules\Organisation\Application\Contracts\ActiveOrganisationMemberships;
use App\Modules\Organisation\Application\Data\OrganisationSummary;

final readonly class ListOrganisations
{
    public function __construct(
        private ActiveOrganisationMemberships $memberships,
    ) {}

    /**
     * @return list<OrganisationSummary>
     */
    public function handle(int $userId): array
    {
        return $this->memberships->forUser($userId);
    }
}
