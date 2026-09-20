<?php

namespace App\Modules\Organisation\Application\Contracts;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\OrganisationSummary;

interface OrganisationDetails
{
    public function get(int $organisationId, OrganisationRole $role): OrganisationSummary;
}
