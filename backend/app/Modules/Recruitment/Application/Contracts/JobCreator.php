<?php

namespace App\Modules\Recruitment\Application\Contracts;

use App\Modules\Recruitment\Application\Data\JobData;
use App\Modules\Recruitment\Application\Data\JobRecord;

interface JobCreator
{
    public function create(int $organisationId, JobData $data): JobRecord;
}
