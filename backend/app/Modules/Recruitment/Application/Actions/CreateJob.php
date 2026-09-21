<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\JobCreator;
use App\Modules\Recruitment\Application\Data\JobData;
use App\Modules\Recruitment\Application\Data\JobRecord;

final readonly class CreateJob
{
    public function __construct(private JobCreator $jobs) {}

    public function handle(TenantContext $tenant, JobData $data): JobRecord
    {
        return $this->jobs->create($tenant->organisationId, $data);
    }
}
