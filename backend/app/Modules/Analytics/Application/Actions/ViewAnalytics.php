<?php

namespace App\Modules\Analytics\Application\Actions;

use App\Modules\Analytics\Application\Contracts\AnalyticsReadModel;
use App\Modules\Analytics\Application\Data\AnalyticsPeriod;
use App\Modules\Analytics\Application\Data\AnalyticsSnapshot;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ViewAnalytics
{
    public function __construct(private AnalyticsReadModel $analytics) {}

    public function handle(TenantContext $tenant, AnalyticsPeriod $period): AnalyticsSnapshot
    {
        return $this->analytics->forOrganisation($tenant->organisationId, $period);
    }
}
