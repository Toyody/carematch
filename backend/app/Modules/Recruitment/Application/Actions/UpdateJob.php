<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\JobUpdater;
use App\Modules\Recruitment\Application\Data\JobRecord;
use App\Modules\Recruitment\Application\Exceptions\JobNotFound;

final readonly class UpdateJob
{
    public function __construct(private JobUpdater $jobs) {}

    /** @param array<string, mixed> $changes */
    public function handle(TenantContext $tenant, int $jobId, array $changes): JobRecord
    {
        return $this->jobs->update($tenant->organisationId, $jobId, $changes)
            ?? throw new JobNotFound;
    }
}
