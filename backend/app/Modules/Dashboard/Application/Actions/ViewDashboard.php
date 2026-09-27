<?php

namespace App\Modules\Dashboard\Application\Actions;

use App\Modules\Dashboard\Application\Contracts\DashboardReadModel;
use App\Modules\Dashboard\Application\Data\DashboardSnapshot;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ViewDashboard
{
    public function __construct(
        private DashboardReadModel $dashboard,
    ) {}

    public function handle(TenantContext $tenant): DashboardSnapshot
    {
        return $this->dashboard->forOrganisation($tenant->organisationId);
    }
}
