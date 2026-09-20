<?php

namespace App\Modules\Organisation\Application\Actions;

use App\Modules\Organisation\Application\Contracts\OrganisationDetails;
use App\Modules\Organisation\Application\Data\OrganisationSummary;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ViewOrganisation
{
    public function __construct(
        private OrganisationDetails $organisations,
    ) {}

    public function handle(TenantContext $tenant): OrganisationSummary
    {
        return $this->organisations->get($tenant->organisationId, $tenant->role);
    }
}
