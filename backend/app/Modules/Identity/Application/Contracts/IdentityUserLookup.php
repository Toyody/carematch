<?php

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\Data\IdentityUser;

interface IdentityUserLookup
{
    public function findByCanonicalEmail(string $email): ?IdentityUser;

    public function findById(int $userId): ?IdentityUser;
}
