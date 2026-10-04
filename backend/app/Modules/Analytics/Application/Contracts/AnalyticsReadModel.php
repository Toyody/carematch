<?php

namespace App\Modules\Analytics\Application\Contracts;

use App\Modules\Analytics\Application\Data\AnalyticsPeriod;
use App\Modules\Analytics\Application\Data\AnalyticsSnapshot;

interface AnalyticsReadModel
{
    public function forOrganisation(int $organisationId, AnalyticsPeriod $period): AnalyticsSnapshot;
}
