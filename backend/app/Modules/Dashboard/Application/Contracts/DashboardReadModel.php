<?php

namespace App\Modules\Dashboard\Application\Contracts;

use App\Modules\Dashboard\Application\Data\DashboardSnapshot;

interface DashboardReadModel
{
    public function forOrganisation(int $organisationId): DashboardSnapshot;
}
