<?php

namespace App\Modules\Organisation\Application\Contracts;

use App\Modules\Organisation\Application\Data\OrganisationInvitationSummary;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use DateTimeImmutable;

interface OrganisationInvitationCreator
{
    public function create(
        int $organisationId,
        int $inviterUserId,
        ?int $inviteeUserId,
        string $email,
        OrganisationRole $role,
        string $tokenHash,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): OrganisationInvitationSummary;
}
