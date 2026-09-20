<?php

namespace App\Modules\Organisation\Application\Contracts;

use App\Modules\Organisation\Application\Data\OrganisationInvitationSummary;

interface OrganisationInvitationLister
{
    /**
     * @return list<OrganisationInvitationSummary>
     */
    public function forOrganisation(int $organisationId): array;
}
