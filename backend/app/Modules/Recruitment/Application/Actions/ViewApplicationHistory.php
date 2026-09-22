<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\ApplicationHistoryReader;
use App\Modules\Recruitment\Application\Data\ApplicationStatusHistoryRecord;
use App\Modules\Recruitment\Application\Exceptions\ApplicationNotFound;

final readonly class ViewApplicationHistory
{
    public function __construct(private ApplicationHistoryReader $history) {}

    /** @return list<ApplicationStatusHistoryRecord> */
    public function handle(TenantContext $tenant, int $applicationId): array
    {
        return $this->history->forApplication($tenant->organisationId, $applicationId)
            ?? throw new ApplicationNotFound;
    }
}
