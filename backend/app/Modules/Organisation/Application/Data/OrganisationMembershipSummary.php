<?php

namespace App\Modules\Organisation\Application\Data;

use DateTimeImmutable;

final readonly class OrganisationMembershipSummary
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $userName,
        public string $userEmail,
        public OrganisationRole $role,
        public ?DateTimeImmutable $deactivatedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
