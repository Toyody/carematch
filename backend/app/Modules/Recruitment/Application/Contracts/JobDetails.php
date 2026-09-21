<?php

namespace App\Modules\Recruitment\Application\Contracts;

use App\Modules\Recruitment\Application\Data\JobRecord;

interface JobDetails
{
    public function find(int $organisationId, int $jobId): ?JobRecord;
}
