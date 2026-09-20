<?php

namespace App\Modules\Organisation\Application\Data;

use DateTimeImmutable;

final readonly class OrganisationInvitationSummary
{
    public function __construct(
        public int $id,
        public string $email,
        public OrganisationRole $role,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $acceptedAt,
        public ?DateTimeImmutable $revokedAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
