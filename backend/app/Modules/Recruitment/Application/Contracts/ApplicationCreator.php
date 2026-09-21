<?php

namespace App\Modules\Recruitment\Application\Contracts;

use App\Modules\Recruitment\Application\Data\ApplicationSummary;

interface ApplicationCreator
{
    public function create(int $organisationId, int $actorUserId, int $jobId, int $candidateId): ApplicationSummary;
}
