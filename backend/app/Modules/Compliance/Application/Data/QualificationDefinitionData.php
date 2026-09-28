<?php

namespace App\Modules\Compliance\Application\Data;

final readonly class QualificationDefinitionData
{
    public function __construct(
        public string $name,
        public ?string $category,
        public ?string $description,
        public bool $isActive = true,
    ) {}
}
