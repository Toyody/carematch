<?php

namespace App\Modules\Compliance\Domain;

use DateTimeImmutable;

final readonly class CredentialEvidence
{
    public function __construct(
        public int $id,
        public int $qualificationDefinitionId,
        public ?DateTimeImmutable $expiresOn,
    ) {}
}
