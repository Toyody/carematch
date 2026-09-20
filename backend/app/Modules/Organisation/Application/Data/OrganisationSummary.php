<?php

namespace App\Modules\Organisation\Application\Data;

use DateTimeImmutable;

final readonly class OrganisationSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $role,
        public DateTimeImmutable $createdAt,
    ) {}
}
