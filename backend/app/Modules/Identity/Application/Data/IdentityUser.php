<?php

namespace App\Modules\Identity\Application\Data;

final readonly class IdentityUser
{
    public function __construct(
        public int $id,
        public string $email,
    ) {}
}
