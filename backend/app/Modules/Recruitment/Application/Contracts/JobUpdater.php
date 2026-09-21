<?php

namespace App\Modules\Recruitment\Application\Contracts;

use App\Modules\Recruitment\Application\Data\JobRecord;

interface JobUpdater
{
    /** @param array<string, mixed> $changes */
    public function update(int $organisationId, int $jobId, array $changes): ?JobRecord;
}
