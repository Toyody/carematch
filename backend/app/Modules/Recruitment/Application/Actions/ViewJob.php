<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\JobDetails;
use App\Modules\Recruitment\Application\Data\JobRecord;
use App\Modules\Recruitment\Application\Exceptions\JobNotFound;

final readonly class ViewJob
{
    public function __construct(private JobDetails $jobs) {}

    public function handle(TenantContext $tenant, int $jobId): JobRecord
    {
        return $this->jobs->find($tenant->organisationId, $jobId) ?? throw new JobNotFound;
    }
}
