<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\ApplicationCreator;
use App\Modules\Recruitment\Application\Data\ApplicationSummary;

final readonly class CreateApplication
{
    public function __construct(private ApplicationCreator $applications) {}

    public function handle(TenantContext $tenant, int $jobId, int $candidateId): ApplicationSummary
    {
        return $this->applications->create(
            $tenant->organisationId,
            $tenant->userId,
            $jobId,
            $candidateId,
        );
    }
}
