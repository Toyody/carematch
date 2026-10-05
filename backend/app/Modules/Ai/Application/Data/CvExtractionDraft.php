<?php

namespace App\Modules\Ai\Application\Data;

final readonly class CvExtractionDraft
{
    public function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public ?string $phone,
        public ?string $occupation,
        public ?string $location,
    ) {}

    /** @return array{first_name: ?string, last_name: ?string, email: ?string, phone: ?string, occupation: ?string, location: ?string} */
    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'occupation' => $this->occupation,
            'location' => $this->location,
        ];
    }
}
