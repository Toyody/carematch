<?php

namespace App\Modules\Candidate\Application\Data;

final readonly class CandidateData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $phone,
        public ?string $occupation,
        public ?string $location,
        public ?string $availability,
        public ?string $notes,
    ) {}
}
