<?php

namespace App\Modules\Recruitment\Application\Contracts;

use App\Modules\Recruitment\Application\Data\JobListCriteria;
use App\Modules\Recruitment\Application\Data\JobPage;

interface JobLister
{
    public function list(int $organisationId, JobListCriteria $criteria): JobPage;
}
