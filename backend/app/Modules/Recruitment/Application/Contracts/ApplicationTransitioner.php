<?php

namespace App\Modules\Recruitment\Application\Contracts;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Recruitment\Application\Data\ApplicationRecord;
use App\Modules\Recruitment\Domain\ApplicationStatus;

interface ApplicationTransitioner
{
    public function transition(
        int $organisationId,
        int $applicationId,
        int $actorUserId,
        OrganisationRole $role,
        ApplicationStatus $target,
        ?string $note,
    ): ?ApplicationRecord;
}
