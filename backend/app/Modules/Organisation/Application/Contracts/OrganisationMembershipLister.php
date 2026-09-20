<?php

namespace App\Modules\Organisation\Application\Contracts;

use App\Modules\Organisation\Application\Data\OrganisationMembershipRecord;
use App\Modules\Organisation\Application\Data\TenantContext;

interface OrganisationMembershipLister
{
    /**
     * @return list<OrganisationMembershipRecord>
     */
    public function forOrganisation(TenantContext $tenant): array;
}
