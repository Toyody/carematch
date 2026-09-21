<?php

namespace App\Modules\Candidate\Application\Data;

final readonly class CandidateReference
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
    ) {}
}
