<?php

namespace App\Modules\Organisation\Application\Contracts;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\OrganisationSummary;

interface OrganisationNameUpdater
{
    public function updateName(
        int $organisationId,
        int $actorUserId,
        string $name,
        OrganisationRole $role,
    ): OrganisationSummary;
}
