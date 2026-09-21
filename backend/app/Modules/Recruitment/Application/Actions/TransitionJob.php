<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\JobLifecycleTransitioner;
use App\Modules\Recruitment\Application\Data\JobRecord;
use App\Modules\Recruitment\Application\Exceptions\JobNotFound;
use App\Modules\Recruitment\Domain\JobStatus;

final readonly class TransitionJob
{
    public function __construct(private JobLifecycleTransitioner $jobs) {}

    public function handle(TenantContext $tenant, int $jobId, JobStatus $target): JobRecord
    {
        return $this->jobs->transition($tenant->organisationId, $jobId, $target)
            ?? throw new JobNotFound;
    }
}
