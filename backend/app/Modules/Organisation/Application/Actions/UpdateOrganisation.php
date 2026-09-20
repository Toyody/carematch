<?php

namespace App\Modules\Organisation\Application\Actions;

use App\Modules\Organisation\Application\Contracts\OrganisationNameUpdater;
use App\Modules\Organisation\Application\Data\OrganisationSummary;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class UpdateOrganisation
{
    public function __construct(
        private OrganisationNameUpdater $organisations,
    ) {}

    public function handle(TenantContext $tenant, string $name): OrganisationSummary
    {
        return $this->organisations->updateName(
            organisationId: $tenant->organisationId,
            name: $name,
            role: $tenant->role,
        );
    }
}
