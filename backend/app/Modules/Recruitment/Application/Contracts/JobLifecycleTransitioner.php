<?php

namespace App\Modules\Recruitment\Application\Contracts;

use App\Modules\Recruitment\Application\Data\JobRecord;
use App\Modules\Recruitment\Domain\JobStatus;

interface JobLifecycleTransitioner
{
    public function transition(int $organisationId, int $jobId, JobStatus $target): ?JobRecord;
}
