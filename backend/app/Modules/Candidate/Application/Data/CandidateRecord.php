<?php

namespace App\Modules\Candidate\Application\Data;

use DateTimeImmutable;

final readonly class CandidateRecord
{
    public function __construct(
        public int $id,
        public int $organisationId,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $phone,
        public ?string $occupation,
        public ?string $location,
        public ?string $availability,
        public ?string $notes,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
