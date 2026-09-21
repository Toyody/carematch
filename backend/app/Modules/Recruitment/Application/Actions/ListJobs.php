<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\JobLister;
use App\Modules\Recruitment\Application\Data\JobListCriteria;
use App\Modules\Recruitment\Application\Data\JobPage;

final readonly class ListJobs
{
    public function __construct(private JobLister $jobs) {}

    public function handle(TenantContext $tenant, JobListCriteria $criteria): JobPage
    {
        return $this->jobs->list($tenant->organisationId, $criteria);
    }
}
