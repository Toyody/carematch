<?php

namespace App\Modules\Organisation\Application\Actions;

use App\Modules\Organisation\Application\Contracts\OrganisationInvitationRevoker;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;
use DateTimeZone;

final readonly class RevokeOrganisationInvitation
{
    public function __construct(
        private OrganisationInvitationRevoker $invitations,
    ) {}

    public function handle(TenantContext $tenant, int $invitationId): void
    {
        $this->invitations->revoke(
            organisationId: $tenant->organisationId,
            invitationId: $invitationId,
            revokedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }
}
