<?php

namespace App\Modules\Recruitment\Application\Contracts;

use App\Modules\Recruitment\Application\Data\ApplicationStatusHistoryRecord;

interface ApplicationHistoryReader
{
    /** @return list<ApplicationStatusHistoryRecord>|null */
    public function forApplication(int $organisationId, int $applicationId): ?array;
}
