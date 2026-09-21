<?php

namespace App\Modules\Recruitment\Application\Contracts;

use App\Modules\Recruitment\Application\Data\ApplicationListCriteria;
use App\Modules\Recruitment\Application\Data\ApplicationPage;

interface ApplicationLister
{
    public function list(int $organisationId, ApplicationListCriteria $criteria): ApplicationPage;
}
