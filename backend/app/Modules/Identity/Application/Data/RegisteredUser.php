<?php

namespace App\Modules\Identity\Application\Data;

use DateTimeImmutable;

final readonly class RegisteredUser
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public DateTimeImmutable $createdAt,
    ) {}
}
