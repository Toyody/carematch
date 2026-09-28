<?php

namespace App\Modules\Compliance\Application\Data;

use DateTimeImmutable;

final readonly class QualificationDefinitionRecord
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $category,
        public ?string $description,
        public bool $isActive,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
