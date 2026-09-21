<?php

namespace App\Modules\Recruitment\Application\Contracts;

use App\Modules\Recruitment\Application\Data\ApplicationRecord;

interface ApplicationDetails
{
    public function find(int $organisationId, int $applicationId): ?ApplicationRecord;
}
