<?php

namespace App\Modules\Organisation\Application\Contracts;

use App\Modules\Organisation\Application\Data\OrganisationSummary;

interface ActiveOrganisationMemberships
{
    /**
     * @return list<OrganisationSummary>
     */
    public function forUser(int $userId): array;
}
