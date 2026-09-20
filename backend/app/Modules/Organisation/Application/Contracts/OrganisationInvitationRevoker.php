<?php

namespace App\Modules\Organisation\Application\Contracts;

use DateTimeImmutable;

interface OrganisationInvitationRevoker
{
    public function revoke(
        int $organisationId,
        int $invitationId,
        DateTimeImmutable $revokedAt,
    ): void;
}
