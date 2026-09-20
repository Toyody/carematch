<?php

namespace App\Modules\Organisation\Application\Contracts;

use DateTimeImmutable;

interface OrganisationInvitationAcceptor
{
    public function accept(
        string $tokenHash,
        int $userId,
        string $canonicalEmail,
        DateTimeImmutable $acceptedAt,
    ): void;
}
