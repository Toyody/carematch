<?php

namespace App\Modules\Organisation\Application\Actions;

use App\Modules\Organisation\Application\Contracts\OrganisationInvitationLister;
use App\Modules\Organisation\Application\Data\OrganisationInvitationSummary;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ListOrganisationInvitations
{
    public function __construct(
        private OrganisationInvitationLister $invitations,
    ) {}

    /**
     * @return list<OrganisationInvitationSummary>
     */
    public function handle(TenantContext $tenant): array
    {
        return $this->invitations->forOrganisation($tenant->organisationId);
    }
}
